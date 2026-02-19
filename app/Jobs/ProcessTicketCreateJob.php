<?php

namespace App\Jobs;

use App\Services\ItopServiceBuilder;
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
        info('update JSON payload');
        info($ticket);

        /* catatan: untuk contact tidak perlu di sync. Hanya ticket saya dan attachment-nya */
        $payload = self::generatePayload($ticket);
        info('payload JSON');
        info($payload);

        /* call Itop Elitery API */
        $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        $response = $service->callApi($payload);
        info('response from itop API');
        info($response);
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
