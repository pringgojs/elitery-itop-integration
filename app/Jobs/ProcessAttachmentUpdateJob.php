<?php

namespace App\Jobs;

use App\Helpers\TicketMappingSync;
use App\Models\TicketMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pringgojs\LaravelItop\Models\Attachment;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;
use Pringgojs\LaravelItop\Utils\ResponseNormalizer;

class ProcessAttachmentUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $objectSupported = ['UserRequest', 'Incident', 'Problem', 'Change'];
    public $ticketId;
    public $attachmentId;
    public $connection;
    public $mapping;
    public $ticket;
    public $service;
    // added for bidirectional sync
    public $source; // 'elitery' or 'external'
    public $sourceTicket;
    public $targetService;
    public $targetDb;
    public $targetTicketId;
    public $invalid = false;

    /**
     * Create a new job instance.
     */
    public function __construct($attachmentId, $source)
    {
        info('Initializing ProcessAttachmentUpdateJob with attachmentId: ' . $attachmentId . ' source: ' . $source);
        $this->attachmentId = $attachmentId;

        // treat $connection as the source system for bidirectional sync
        $this->source = $source;

        if ($this->source == 'elitery') {
            \info('Source is elitery, looking for attachment on elitery DB');
            $attachment = Attachment::on(env('DB_ITOP_ELITERY'))->whereId($attachmentId)->first();
            $class = $attachment->item_class;

            if (!in_array($class, $this->objectSupported)) {
                info("Attachment with id " . $attachmentId . " has unsupported item_class: " . $class);
                $this->invalid = true;
                return;
            }

            
            $this->sourceTicket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($attachment->item_id)->first();
            $this->mapping = TicketMapping::where('elitery_ticket_id', $this->sourceTicket->id)->first();
            $this->ticketId = $this->sourceTicket->id;

            // target is external
            $this->targetService = new ApiService(env('ITOP_EXTERNAL_BASE_URL'), env('ITOP_EXTERNAL_USERNAME'), env('ITOP_EXTERNAL_PASSWORD'));
            $this->targetDb = env('DB_ITOP_EXTERNAL');
            $this->targetTicketId = $this->mapping->external_ticket_id ?? null;
        } else {
            info('Source is external, looking for attachment on external DB');
            $attachment = Attachment::on(env('DB_ITOP_EXTERNAL'))->whereId($attachmentId)->first();
            $class = $attachment->item_class;

            if (!in_array($class, $this->objectSupported)) {
                info("Attachment with id " . $attachmentId . " has unsupported item_class: " . $class);
                $this->invalid = true;
                return;
            }

            $this->sourceTicket = Ticket::on(env('DB_ITOP_EXTERNAL'))->whereId($attachment->item_id)->first();
            $this->mapping = TicketMapping::where('external_ticket_id', $this->sourceTicket->id)->first();
            $this->ticketId = $this->sourceTicket->id;

            info('mapping external_ticket_id: '.$this->sourceTicket->id);
            info('mapping data: '.json_encode($this->mapping));
            // target is elitery
            $this->targetService = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
            $this->targetDb = env('DB_ITOP_ELITERY');
            $this->targetTicketId = $this->mapping->elitery_ticket_id ?? null;
        }

        if (! $this->mapping || ! $this->targetTicketId || ! $this->sourceTicket) {
            info("No mapping or ticket found for ticket id: " . $this->ticketId);
            $this->invalid = true;
            return;
        }
    }

    /**
     * process update attachment dari external ke internal elitery atau sebaliknya, tergantung dari connection yang di set di constructor. Proses ini akan menghapus seluruh attachment yang ada di internal elitery atau external itop dan membuat ulang attachment sesuai dengan yang ada di external itop atau internal elitery. Proses ini juga akan melakukan sync mapping antara ticket external dan internal elitery.
     */
    public function handle(): void
    {
        info('Start ProcessUpdateAttachmentJob for ticket id: ' . $this->ticketId . ' source: ' . $this->source);

        if ($this->invalid) {
            info('Invalid job state, aborting ProcessUpdateAttachmentJob for id: ' . $this->ticketId);
            return;
        }

        if ($this->mapping->is_stop_sync) {
            info("Stop sync is true for ticket id: " . $this->ticketId);
            $this->mapping->is_stop_sync = false;
            $this->mapping->save();
            return;
        }

        info("Executing bidirectional attachment sync...");

        // remove attachments on target system
        $this->removeAttachment();

        // generate payload for update ticket on target system
        $updatePayload = $this->generatePayload($this->sourceTicket, $this->targetTicketId);

        // call API update ticket on target
        $updateTicket = $this->targetService->callApi($updatePayload);

        // normalize response update ticket
        $normalizedTicket = ResponseNormalizer::normalizeItopUpdateResponse($updateTicket);

        // create attachments on target using source attachments
        $this->createAttachment($this->sourceTicket, $normalizedTicket);

        // sync mapping (external id first, internal second) — keep existing helper contract
        if ($this->source == 'elitery') {
            $externalId = $this->mapping->external_ticket_id;
            $internalId = $this->mapping->elitery_ticket_id;
        } else {
            $externalId = $this->mapping->external_ticket_id;
            $internalId = $this->mapping->elitery_ticket_id;
        }

        info('Syncing mapping with externalId: ' . $externalId . ' internalId: ' . $internalId);
        TicketMappingSync::sync($externalId, $internalId, $this->sourceTicket->finalclass);

        info("ProcessUpdateAttachmentJob done for ticket id: " . $this->ticketId);
        
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
        info('Start ProcessUpdateAttachmentJob - removeAttachment on target DB: ' . $this->targetDb);

        /* get ticket from target database */
        $ticket = Ticket::on($this->targetDb)->whereId($this->targetTicketId)->first();
        if (! $ticket) {
            info('Target ticket not found: ' . $this->targetTicketId);
            return;
        }

        /* generate payload for attachment delete on target */
        foreach ($ticket->attachments as $attachment) {
            $payload = ItopServiceBuilder::payloadAttachmentDelete([
                'key' => $attachment->id
            ]);

            $response = $this->targetService->callApi($payload);
            info('attachment deleted on target with response: ' . json_encode($response));
        }

        info('Completed removeAttachment on target DB: ' . $this->targetDb);
    }

    public function createAttachment($ticket, $normalizedTicket)
    {
        info('start createAttachment on target for ticket id: ' . $ticket->id);
        $attachments = $ticket->attachments;

        if (! $attachments) return;

        info('generate payload for attachment create on target');
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

            $response = $this->targetService->callApi($payload);
        }
    }
}
