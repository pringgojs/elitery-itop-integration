<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pringgojs\LaravelItop\Models\Ticket;

class ProcessTicketUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticketId;
    public $dbConnection;

    /**
     * Create a new job instance.
     */
    public function __construct($dbConnection, $ticketId)
    {
        $this->dbConnection = $dbConnection;
        $this->ticketId = $ticketId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ticket = Ticket::on($this->dbConnection)->whereId($this->ticketId)->first();
        info('update JSON payload');
        info($ticket);
        $contact = $ticket->contacts;
        info($contact);
    }
}
