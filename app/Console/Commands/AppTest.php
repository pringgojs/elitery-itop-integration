<?php

namespace App\Console\Commands;

use App\Helpers\InlineImageHelper;
use Illuminate\Console\Command;

class AppTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:app-test';

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
        $description = '<p>
            test
        </p>
        <figure class="image image_resized">
            <img style="aspect-ratio:1600/913" src="https://cms.ebconnect.com/pages/ajax.document.php?operation=download_inlineimage&amp;id=91&amp;s=581595" width="1600" height="913" data-img-id="91" data-img-secret="581595">
        </figure>';
        $description = InlineImageHelper::unwrapFigureTags($description);
        dd($description); 

    }
}
