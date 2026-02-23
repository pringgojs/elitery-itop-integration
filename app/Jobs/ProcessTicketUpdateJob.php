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
use Pringgojs\LaravelItop\Services\ResponseNormalizer;

class ProcessTicketUpdateJob implements ShouldQueue
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
        $this->service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        $this->mapping = TicketMapping::where('external_ticket_id', $ticketId)->first();
        if (! $this->mapping) {
            info("No mapping found for ticket id: " . $ticketId);
            return;
        }
    }
    /**
     * PROSES UPDATE TIKET DARI EXTERNAL KE INTERNAL ELITERY. 
     * PROSES INI AKAN MENGHAPUS SELURUH ATTACHMENT YANG ADA DI INTERNAL ELITERY DAN MEMBUAT ULANG ATTACHMENT SESUAI DENGAN YANG ADA DI EXTERNAL ITOP. 
     * PROSES INI JUGA AKAN MELAKUKAN SYNC MAPPING ANTARA TICKET EXTERNAL DAN INTERNAL ELITERY.
     */

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        info('Start ProcessTicketUpdateJob for ticket id: ' . $this->ticketId);

        if ($this->mapping->is_stop_sync) {
            info("Stop sync is true for ticket id: " . $this->ticketId);
            // set is_stop_sync to false, agar proses update berikutnya bisa berjalan normal
            $this->mapping->is_stop_sync = false;
            $this->mapping->save();
            return;
        }

        info("EKSEKUSI YA....");
        // get ticket
        $ticket = Ticket::on(env('DB_ITOP_EXTERNAL'))->whereId($this->ticketId)->first();
        
        // remove attachment
        self::removeAttachment();
        
        // generate payload for update ticket
        $updatePayload = $this->generatePayload($ticket, $this->mapping->elitery_ticket_id);

        // call API update ticket
        $updateTicket = $this->service->callApi($updatePayload);

        // normalize response update ticket
        $normalizedTicket = ResponseNormalizer::normalizeItopUpdateResponse($updateTicket);
        self::createAttachment($ticket, $normalizedTicket);

        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $ticket->id,
            $internalTicketId = $normalizedTicket['object']['id'],
            $ticket->finalclass);

        info("ProcessTicketUpdateJob done for ticket id: " . $ticket->id);
        
    }

    public function generatePayload($ticket, $internalTicketId)
    {
        $payload = [
            'operation' => 'core/update',
            'comment' => 'ticket updated from API',
            'class' => $ticket->finalclass,
            'output_fields' => 'id, ref, title, status',
            'org_id' => env('ORG_ID_ITOP_ELITERY', 2),
            'caller_id' => env('CALLER_ID_ITOP_ELITERY', 12),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'private_log' => $ticket->getPrivateLog(),
            'key' => $internalTicketId
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }

    public function removeAttachment()
    {
        info('Start ProcessTicketUpdateJob - removeAttachment');
        
        /* get ticket from elitery database */
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->mapping->elitery_ticket_id)->first();
        
        /* generate payload for attachment delete */
        foreach ($ticket->attachments as $attachment) {
            $payload = ItopServiceBuilder::payloadAttachmentDelete([
                'key' => $attachment->id
            ]);

            $response = $this->service->callApi($payload);
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

            $response = $this->service->callApi($payload);
        }
    }
}
