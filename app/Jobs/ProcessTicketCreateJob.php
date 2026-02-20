<?php

namespace App\Jobs;

use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;

class ProcessTicketCreateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticketId;

    /**
     * Create a new job instance.
     */
    public function __construct($ticketId)
    {
        $this->ticketId = $ticketId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \info('Start ProcessTicketCreateJob');
        
        $ticket = Ticket::on(env('DB_ITOP_EXTERNAL'))->whereId($this->ticketId)->first();

        /* call Itop Elitery API */
        $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        $newTicket = $service->callApi($this->generatePayload($ticket));
        $normalizedTicket = ItopServiceBuilder::normalizeItopCreateResponse($newTicket);
        info('ticket created');
        info($normalizedTicket);
        
        $attachments = $ticket->attachments;

        if (!$attachments) return;

        info('generate payload for attachment create');
        foreach ($attachments as $attachment) {
            $payload = ItopServiceBuilder::payloadAttachmentCreate([
                'item_class' => $ticket->finalclass,
                'item_id' => $normalizedTicket['object']['id'],
                'item_org_id' => env('ORG_ID_ITOP_ELITERY', 2),
                'contents' => [
                    'filename' => $attachment->contents_filename,
                    'mimetype' => $attachment->contents_mimetype,
                    'binary' => base64_encode($attachment->contents_data),
                ]
            ]);

            $response = $service->callApi($payload);
        }

        info('End ProcessTicketCreateJob');
    }

    public function generatePayload($ticket)
    {
        $payload= [
            'operation' => 'core/create',
            'comment' => 'ticket created from API',
            'class' => $ticket->finalclass,
            'output_fields' => 'id, ref, title, status',
            'org_id' => env('ORG_ID_ITOP_ELITERY', 2),
            'caller_id' => env('CALLER_ID_ITOP_ELITERY', 12),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => 'new',
            'private_log' => $ticket->getPrivateLog()
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }
    
}
