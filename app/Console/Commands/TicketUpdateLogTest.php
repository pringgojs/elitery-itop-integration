<?php

namespace App\Console\Commands;

use App\Helpers\TicketMappingSync;
use App\Models\TicketMapping;
use Illuminate\Console\Command;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;

class TicketUpdateLogTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ticket-update-log-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public $ticketId = 23;
    public $mapping;

    public function __construct()
    {
        parent::__construct();

        $this->mapping = TicketMapping::where('elitery_ticket_id', $this->ticketId)->first();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \info('Start ProcessTicketUpdateLogFromEliteryJob');
        
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId($this->ticketId)->first();

        /* call Itop Elitery API */
        $service = new ApiService(env('ITOP_EXTERNAL_BASE_URL'), env('ITOP_EXTERNAL_USERNAME'), env('ITOP_EXTERNAL_PASSWORD'));
        $exec = $service->callApi($this->generatePayload($ticket));

        //sync mapping
        $dd = TicketMappingSync::sync(
            $externalTicketId = $this->mapping->external_ticket_id,
            $internalTicketId = $ticket->id,
            $ticket->finalclass);

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
