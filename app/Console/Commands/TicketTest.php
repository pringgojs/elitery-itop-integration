<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Pringgojs\LaravelItop\Models\Ticket;

class TicketTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ticket-test';

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
        $ticket = Ticket::on(env('DB_ITOP_ELITERY'))->whereId(53339)->first();

    }
}
