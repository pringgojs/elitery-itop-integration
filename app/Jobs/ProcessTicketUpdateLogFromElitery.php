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

class ProcessTicketUpdateLogFromElitery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticketId;
    public $mapping;

    /**
     * Create a new job instance.
     */
    public function __construct($ticketId)
    {
        $this->ticketId = $ticketId;
        $this->mapping = TicketMapping::where('elitery_ticket_id', $this->ticketId)->first();

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \info('Start ProcessTicketUpdateLogFromEliteryJob');
        
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->ticketId)->first();

        //sync mapping harus dipanggil sebelum proses update, agar field is_stop_sync bisa di set true, sehingga tidak terjadi loop update yang tidak berujung antara elitery dan itop external
        $result = TicketMappingSync::sync(
            $externalTicketId = $this->mapping->external_ticket_id,
            $internalTicketId = $ticket->id,
            $ticket->finalclass,
            $isStopSync = true); // stop sync true, agar tidak terjadi loop update yang tidak berujung antara elitery dan itop external
        
        info('Mapping sync result: ');
        info($result);
        // return;
        /* call Itop Elitery API */
        $service = new ApiService(env('ITOP_EXTERNAL_BASE_URL'), env('ITOP_EXTERNAL_USERNAME'), env('ITOP_EXTERNAL_PASSWORD'));
        $payload = $this->generatePayload($ticket);
        
        $exec = $service->callApi($this->generatePayload($ticket));

        
        info('End ProcessTicketUpdateLogFromEliteryJob');
    }

    public function generatePayload($ticket)
    {
        $payload= [
            'operation' => 'core/update',
            'comment' => 'ticket updated from API',
            'class' => $ticket->finalclass,
            'output_fields' => 'id, ref, title, status',
            'private_log' => $ticket->getPrivateLog(),
            'key' => $this->mapping->external_ticket_id
        ];

        return ItopServiceBuilder::payloadTicketCreate($payload);
    }
}
