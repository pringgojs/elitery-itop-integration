<?php

namespace App\Jobs;

use App\Helpers\TicketMappingSync;
use App\Models\TicketMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Pringgojs\LaravelItop\Utils\ArrayHelper;

class ProcessTicketStateChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticketId;
    public $service;
    public $mapping;

    /**
     * Create a new job instance.
     */
    public function __construct($ticketId)
    {
        $this->ticketId = $ticketId;
        $this->service = new ApiService(env('ITOP_EXTERNAL_BASE_URL'), env('ITOP_EXTERNAL_USERNAME'), env('ITOP_EXTERNAL_PASSWORD'));
        $this->mapping = TicketMapping::where('elitery_ticket_id', $ticketId)->first();

        if (! $this->mapping) {
            info("No mapping found for ticket id: " . $ticketId);
            return;
        }
    }

    /**
     * Execute the job. Poses update ticket state change from internal elitery to external itop. This job will be dispatched when there is a state change in the elitery ticket.
     */
    public function handle(): void
    {
        // get ticket
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->ticketId)->first();
        
        // generate payload for update ticket
        $updatePayload = $this->generatePayload($ticket);

        // call API update ticket
        $updateTicket = $this->service->callApi($updatePayload);

        // check if there's an error due to invalid stimulus
        if ($this->isInvalidStimulusError($updateTicket)) {
            info('Invalid stimulus detected, attempting to re-open ticket first');
            
            // re-open the ticket
            $reopenPayload = $this->generatePayload($ticket, 'ev_reopen');
            $reopenResponse = $this->service->callApi($reopenPayload);
            
            info('Re-open response:', $reopenResponse);
            
            // if re-open was successful, try the original state change again
            if (!$this->hasError($reopenResponse)) {
                $updateTicket = $this->service->callApi($updatePayload);
                info('Retry after re-open:', $updateTicket);
            }
        }

        
        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $this->mapping->external_ticket_id,
            $internalTicketId = $this->ticketId,
            $ticket->finalclass);

        info("ProcessTicketUpdateJob done for ticket id: " . $ticket->id);
        
    }

    public function generatePayload($ticketElitery, $stimulus = null)
    {
        info('Generating payload for ticket state change for ticket id: ' . $ticketElitery->id);
        info('Current status of ticket: ' . $ticketElitery->status(true));

        $payload = [
            'operation' => 'core/apply_stimulus',
            'comment' => 'ticket updated state from API',
            'class' => $ticketElitery->finalclass,
            'key' => $this->mapping->external_ticket_id,
            'stimulus' => $stimulus ?? ArrayHelper::getStimulusForStatus($ticketElitery->status(true)),
            'fields' => [
                // additional fields can be added here if needed
            ]
        ];

        if ($stimulus == 'ev_reopen') {
            $payload['private_log'] = 'Ticket re-opened for state change';
            info('Using provided stimulus: ' . $stimulus);
        } else {
            if ($ticketElitery->status(true) === 'assigned' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
                // $payload['fields']['agent_id'] = $ticketElitery->type()->pending_reason;
                info('Ticket assigned');
                $payload['private_log'] = 'Ticket Assigned';
            }
    
            if ($ticketElitery->status(true) === 'pending' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
                info('Ticket pending');
                $pendingReason = $ticketElitery->type()->pending_reason;
                info('pending_reason value: ' . var_export($pendingReason, true));
                $payload['fields']['pending_reason'] = $pendingReason;
                $payload['private_log'] = 'Ticket Pending with reason: ' . $pendingReason;
            }
    
            if ($ticketElitery->status(true) === 'resolved' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
                info('Ticket resolved');
                $payload['fields']['resolution_code'] = $ticketElitery->type()->resolution_code;
                $payload['fields']['solution'] = strip_tags($ticketElitery->type()->solution);
                $payload['private_log'] = 'Ticket Resolved';
            }
    
            if ($ticketElitery->status(true) === 'closed' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
                info('Ticket closed');
                $payload['private_log'] = 'Ticket Closed';
                $payload['fields']['user_comment'] = strip_tags($ticketElitery->type()->user_commment);
            }
        }


        info('Generated payload for ticket state change', $payload);

        return ItopServiceBuilder::payloadTicketUpdateState($payload);
    }

    /**
     * Check if the API response contains an error about invalid stimulus.
     */
    private function isInvalidStimulusError(array $response): bool
    {
        return isset($response['code']) && 
               $response['code'] === 100 && 
               isset($response['message']) && 
               strpos($response['message'], 'Invalid stimulus') !== false;
    }

    /**
     * Check if the API response contains any error.
     */
    private function hasError(array $response): bool
    {
        return isset($response['code']) && $response['code'] !== 0;
    }
}
