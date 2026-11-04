<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\CategoryMapper;
use Illuminate\Console\Command;

class RecategorizeBooks extends Command
{
    protected $signature = 'books:recategorize {--dry-run : Show the result without saving anything}';

    protected $description = 'Group the many fine-grained book categories into a few broad ones';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = [];
        $general = [];

        Book::query()->orderBy('id')->chunkById(100, function ($books) use ($dryRun, &$counts, &$general) {
            foreach ($books as $book) {
                $new = CategoryMapper::map($book->category, $book->title . ' ' . $book->description);

                $counts[$new] = ($counts[$new] ?? 0) + 1;

                if ($new === CategoryMapper::GENERAL) {
                    $general[] = "{$book->title} [{$book->category}]";
                }

                if (! $dryRun && $new !== $book->category) {
                    $book->update(['category' => $new]);
                }
            }
        });

        arsort($counts);

        $this->table(
            ['Category', 'Books'],
            collect($counts)->map(fn ($total, $name) => [$name, $total])->values()->all()
        );

        if ($general) {
            $this->warn(count($general) . ' book(s) fell into "' . CategoryMapper::GENERAL . '":');
            foreach ($general as $line) {
                $this->line('  - ' . $line);
            }
        }

        $this->info($dryRun ? 'Dry run only, nothing was saved.' : 'Categories updated.');

        return self::SUCCESS;
    }
}
