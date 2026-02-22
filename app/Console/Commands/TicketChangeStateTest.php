<?php

namespace App\Console\Commands;

use App\Helpers\TicketMappingSync;
use App\Models\TicketMapping;
use Illuminate\Console\Command;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Pringgojs\LaravelItop\Utils\ArrayHelper;

class TicketChangeStateTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ticket-change-state-test';

    public $ticketId = 23; // Set this to the ID of the ticket you want to test
    public $service;
    public $mapping;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ApiService(env('ITOP_EXTERNAL_BASE_URL'), env('ITOP_EXTERNAL_USERNAME'), env('ITOP_EXTERNAL_PASSWORD'));
        $this->mapping = TicketMapping::where('elitery_ticket_id', $this->ticketId)->first();
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // get ticket
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->ticketId)->first();
        
        // generate payload for update ticket
        $updatePayload = $this->generatePayload($ticket);
        dd($updatePayload);

        info('Generated payload for ticket state change', $updatePayload);
        return;

        // call API update ticket
        $updateTicket = $this->service->callApi($updatePayload);
        info('update success');
        info($updateTicket);
        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $this->mapping->external_ticket_id,
            $internalTicketId = $this->ticketId,
            $ticket->finalclass);

        info("ProcessTicketUpdateJob done for ticket id: " . $ticket->id);
        
    }

    public function generatePayload($ticketElitery)
    {
        $payload = [
            'operation' => 'core/apply_stimulus',
            'comment' => 'ticket updated state from API',
            'class' => $ticketElitery->finalclass,
            'key' => $this->mapping->external_ticket_id,
            'stimulus' => ArrayHelper::getStimulusForStatus($ticketElitery->status(true)),
            'fields' => [
                // additional fields can be added here if needed
            ]
        ];

        if ($ticketElitery->status(true) === 'assigned' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
            // $payload['fields']['agent_id'] = $ticketElitery->type()->pending_reason;
            info('Ticket assigned');
            $payload['private_log'] = 'Ticket Assigned';
        }

        if ($ticketElitery->status(true) === 'pending' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
            $pendingReason = $ticketElitery->type()->pending_reason;
            info('pending_reason value: ' . var_export($pendingReason, true));
            $payload['fields']['pending_reason'] = $pendingReason;
            $payload['private_log'] = 'Waiting user response';
        }

        if ($ticketElitery->status(true) === 'resolved' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
            info('Ticket resolved');
            $payload['fields']['resolution_code'] = $ticketElitery->type()->resolution_code;
            $payload['fields']['solution'] = $ticketElitery->type()->solution;
            $payload['private_log'] = 'Ticket Resolved';
        }

        if ($ticketElitery->status(true) === 'closed' && (in_array($ticketElitery->finalclass, ['UserRequest', 'Incident']))) {
            info('Ticket closed');
            $payload['private_log'] = 'Ticket Closed';
        }

        info('Generated payload for ticket state change', $payload);

        return ItopServiceBuilder::payloadTicketUpdateState($payload);
    }
}
