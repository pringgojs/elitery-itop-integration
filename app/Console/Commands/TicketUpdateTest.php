<?php

namespace App\Console\Commands;

use App\Helpers\TicketMappingSync;
use App\Models\TicketMapping;
use Illuminate\Console\Command;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Pringgojs\LaravelItop\Services\ResponseNormalizer;

class TicketUpdateTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ticket-update-test';

    public $ticketId = 44;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /* call Itop Elitery API */
        $ticket = Ticket::on(env('DB_ITOP_EXTERNAL'))->whereId($this->ticketId)->first();
        self::removeAttachment();
        
        $mapping = TicketMapping::where('external_ticket_id', $this->ticketId)->first();
        $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        $updatePayload = $this->generatePayload($ticket, $mapping->elitery_ticket_id);
        // dd($updatePayload);
        $newTicket = $service->callApi($updatePayload);

        $normalizedTicket = ResponseNormalizer::normalizeItopUpdateResponse($newTicket);
        self::createAttachment($ticket, $normalizedTicket);

        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $ticket->id,
            $internalTicketId = $normalizedTicket['object']['id'],
            $ticket->finalclass);
            
        dd("done");
    }

    public function generatePayload($ticket, $key)
    {
        $payload= [
            'operation' => 'core/update',
            'key' => $key,
            'comment' => 'ticket updated from API',
            'class' => $ticket->finalclass,
            'output_fields' => 'id, ref, title, status',
            'org_id' => env('ORG_ID_ITOP_ELITERY', 2),
            'caller_id' => env('CALLER_ID_ITOP_ELITERY', 12),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'private_log' => $ticket->getPrivateLog()
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }

    public function removeAttachment()
    {
        info('Start ProcessTicketUpdateJob - removeAttachment');
        $mapping = TicketMapping::where('external_ticket_id', $this->ticketId)->first();
        
        if (!$mapping) return;
        
        /* get ticket from elitery database */
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($mapping->elitery_ticket_id)->first();
        
        /* call Itop Elitery API */
        $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        
        /* generate payload for attachment delete */
        foreach ($ticket->attachments as $attachment) {
            $payload = ItopServiceBuilder::payloadAttachmentDelete([
                'key' => $attachment->id
            ]);

            $response = $service->callApi($payload);
            info('attachment deleted with response: ' . json_encode($response));
        }
    }

    public function createAttachment($ticket, $normalizedTicket)
    {
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
            
            $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
            $response = $service->callApi($payload);
        }
    }

}
