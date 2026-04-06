<?php

namespace App\Jobs;

use App\Helpers\InlineImageHelper;
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
use Pringgojs\LaravelItop\Utils\ResponseNormalizer;

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

        if (! $this->mapping) {
            info("No mapping found for ticket id: " . $this->ticketId);
            return;
        }

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
        $t0 = microtime(true);
        self::removeAttachment();
        $t1 = microtime(true);
        info('removeAttachment duration: ' . round($t1 - $t0, 4) . 's');
        
        // generate payload for update ticket
        $updatePayload = $this->generatePayload($ticket, $this->mapping->elitery_ticket_id);
        info('Generated payload for ticket update');
        info($updatePayload);

        // call API update ticket
        $t0 = microtime(true);
        $updateTicket = $this->service->callApi($updatePayload);
        $t1 = microtime(true);
        info('callApi(updateTicket) duration: ' . round($t1 - $t0, 4) . 's');

        // normalize response update ticket
        $normalizedTicket = ResponseNormalizer::normalizeItopUpdateResponse($updateTicket);
        $t0 = microtime(true);
        self::createAttachment($ticket, $normalizedTicket);
        $t1 = microtime(true);
        info('createAttachment total duration: ' . round($t1 - $t0, 4) . 's');

        info('Update mapping sync');
        //sync mapping
        TicketMappingSync::sync(
            $externalTicketId = $ticket->id,
            $internalTicketId = $normalizedTicket['object']['id'],
            $ticket->finalclass);

        info("ProcessTicketUpdateJob done for ticket id: " . $ticket->id);
        
    }

    public function generatePayload($ticket, $internalTicketId)
    {
        $description = $ticket->description ?? '-';
        // unwrap any <figure> wrappers so only <img> remains
        $description = InlineImageHelper::unwrapFigureTags($description);

        $payload = [
            'operation' => 'core/update',
            'comment' => 'ticket updated from API',
            'class' => $ticket->finalclass,
            'output_fields' => 'id, ref, title, status',
            'org_id' => env('ORG_ID_ITOP_ELITERY', 2),
            'caller_id' => env('CALLER_ID_ITOP_ELITERY', 12),
            'title' => $ticket->title,
            'description' => $description,
            'impact' => $ticket->type()->impact ?? null,
            'urgency' => $ticket->type()->urgency ?? null,
            'priority' => $ticket->type()->priority ?? null,
            'private_log' => $ticket->getPrivateLog(),
            'key' => $internalTicketId
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }

    public function removeAttachment()
    {
        info('Start ProcessTicketUpdateJob - removeAttachment');
        $t0 = microtime(true);

        /* get ticket from elitery database */
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->mapping->elitery_ticket_id)->first();
        if (! $ticket) {
            info('Target ticket not found: ' . $this->mapping->elitery_ticket_id);
            return;
        }

        /* generate payload for attachment delete */
        $count = 0;
        $total = 0;
        foreach ($ticket->attachments as $attachment) {
            $payload = ItopServiceBuilder::payloadAttachmentDelete([
                'key' => $attachment->id
            ]);

            $timg0 = microtime(true);
            $response = $this->service->callApi($payload);
            $timg1 = microtime(true);
            $dur = $timg1 - $timg0;
            $total += $dur;
            $count++;
            info('attachment delete id=' . $attachment->id . ' duration: ' . round($dur, 4) . 's');
        }

        $t1 = microtime(true);
        info('removeAttachment loop count: ' . $count . ' total duration: ' . round($total, 4) . 's overall: ' . round($t1 - $t0, 4) . 's');
    }

    public function createAttachment($ticket, $normalizedTicket)
    {
        $attachments = $ticket->attachments;

        if (!$attachments) return;

        info('generate payload for attachment create');
        $count = 0;
        $total = 0;
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

            $t0 = microtime(true);
            $response = $this->service->callApi($payload);
            $t1 = microtime(true);
            $dur = $t1 - $t0;
            $total += $dur;
            $count++;
            info('attachment create filename=' . ($attachment->contents_filename ?? 'unknown') . ' duration: ' . round($dur, 4) . 's');
        }

        info('createAttachment loop count: ' . $count . ' total duration: ' . round($total, 4) . 's');
    }
}
