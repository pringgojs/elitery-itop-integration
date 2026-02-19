<?php

namespace App\Console\Commands;

use App\Services\ItopServiceBuilder;
use Illuminate\Console\Command;
use Pringgojs\LaravelItop\Models\Ticket;
use Pringgojs\LaravelItop\Services\ApiService;

class ItopAPICallTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:itop-api-call-test';

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
        $ticket = Ticket::on(env('DB_ITOP_EXTERNAL'))->whereId(39)->first();
        $service = new ApiService(env('ITOP_ELITERY_BASE_URL'), env('ITOP_ELITERY_USERNAME'), env('ITOP_ELITERY_PASSWORD'));
        $response = $service->callApi($this->generatePayload($ticket));
        info('response from itop API');
        dd($response);
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
