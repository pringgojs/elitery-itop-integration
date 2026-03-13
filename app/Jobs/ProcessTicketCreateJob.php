<?php

namespace App\Jobs;

use App\Helpers\TicketMappingSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Pringgojs\LaravelItop\Utils\ResponseNormalizer;
use App\Helpers\InlineImageHelper;

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
        $normalizedTicket = ResponseNormalizer::normalizeItopCreateResponse($newTicket);
        info('ticket created');
        info($normalizedTicket);
        
        $attachments = $ticket->attachments ?? [];

        // process regular attachments from the ticket
        if (!empty($attachments)) {
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
        }

        // extract inline images from description and transfer them as attachments
        $pairs = InlineImageHelper::extractFromHtml($ticket->description ?? '');
        $inlineImages = InlineImageHelper::fetchInlineImages($pairs);

        if (!empty($inlineImages)) {
            info('generate payload for inline images');
            foreach ($inlineImages as $inlineImage) {
                $payload = InlineImageHelper::toAttachmentPayload(
                    $inlineImage,
                    $ticket->finalclass,
                    $normalizedTicket['object']['id']
                );

                info('payload for inline image attachment:');
                info($payload);

                $response = $service->callApi($payload);
                info($response);
            }
        }

        // update description
        $newTicket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($normalizedTicket['object']['id'])->first();
        $newTicket->description = InlineImageHelper::adjustDescriptionForDestination($ticket->description ?? '', env('ITOP_ELITERY_BASE_URL'), env('DB_ITOP_ELITERY'));
        $newTicket->save();

        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $ticket->id,
            $internalTicketId = $normalizedTicket['object']['id'],
            $ticket->finalclass);

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
            'impact' => $ticket->type()->impact ?? null,
            'urgency' => $ticket->type()->urgency ?? null,
            'priority' => $ticket->type()->priority ?? null,
            'status' => 'new',
            'private_log' => $ticket->getPrivateLog()
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }
    
}
