<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\CategoryMapper;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportGutenbergBooks extends Command
{
    protected $signature = 'books:import
        {--count=220 : Total books to have in the catalog}
        {--start-page=1 : Gutendex page to start from (use this to resume after a failure)}
        {--max-failures=5 : Stop after this many pages in a row fail}';

    protected $description = 'Import public-domain books (metadata + EPUB links) from Project Gutenberg via Gutendex';

    private const BASE_URL = 'https://gutendex.com/books/';

    public function handle(): int
    {
        $target = (int) $this->option('count');
        $page = max(1, (int) $this->option('start-page'));
        $maxFailures = max(1, (int) $this->option('max-failures'));

        $failuresInARow = 0;
        $skipped = [];

        $this->info("Importing until the catalog has {$target} books (starting at page {$page})...");

        while (Book::count() < $target) {
            $response = $this->fetchPage($page);

            if ($response === null) {
                $failuresInARow++;
                $skipped[] = $page;
                $this->warn("  page {$page} failed after retries");

                if ($failuresInARow >= $maxFailures) {
                    $this->error("Stopped after {$failuresInARow} failed pages in a row. Gutendex looks unavailable.");
                    $this->line("Resume later with: php artisan books:import --count={$target} --start-page={$page}");

                    return self::FAILURE;
                }

                $page++;
                continue;
            }

            $failuresInARow = 0;

            foreach ($response->json('results', []) as $item) {
                if (Book::count() >= $target) {
                    break;
                }

                $formats = $item['formats'] ?? [];
                $epub = $formats['application/epub+zip'] ?? null;
                $cover = $formats['image/jpeg'] ?? null;

                if (! $epub || ! $cover) {
                    continue; // need both a legal download link and a cover
                }

                Book::updateOrCreate(
                    ['gutenberg_id' => $item['id']],
                    [
                        'title'       => Str::limit($item['title'] ?? 'Untitled', 250, ''),
                        'author'      => $this->authorName($item),
                        'category'    => $this->category($item),
                        'description' => $this->description($item),
                        'cover_url'   => $cover,
                        'file_url'    => $epub,
                        'language'    => $item['languages'][0] ?? 'en',
                    ]
                );
            }

            $this->line("  page {$page} done, catalog size: " . Book::count());

            if (! $response->json('next')) {
                $this->warn('Reached the last page Gutendex offers.');
                break;
            }

            $page++;
        }

        $this->info('Done. Total books: ' . Book::count());

        if ($skipped) {
            $this->warn('Skipped pages: ' . implode(', ', $skipped) . '. Re-run with --start-page=<page> to fill them in.');
        }

        if (Book::count() < $target) {
            $this->warn("Catalog is below the target of {$target}. Run the command again later to continue.");
        }

        return self::SUCCESS;
    }

    /**
     * Fetch one Gutendex page. Returns null if it still fails after retries,
     * so a single bad page never crashes the whole import.
     */
    private function fetchPage(int $page): ?Response
    {
        try {
            $response = Http::timeout(30)
                ->retry(3, 2000, throw: false)
                ->get(self::BASE_URL, [
                    'languages' => 'en',
                    'page'      => $page,
                ]);
        } catch (ConnectionException $e) {
            return null;
        }

        return $response->successful() ? $response : null;
    }

    private function authorName(array $item): string
    {
        $name = $item['authors'][0]['name'] ?? 'Unknown Author';

        // Gutenberg stores "Austen, Jane": flip it to "Jane Austen".
        if (str_contains($name, ', ')) {
            [$last, $first] = explode(', ', $name, 2);
            $name = "{$first} {$last}";
        }

        return Str::limit($name, 250, '');
    }

    private function category(array $item): string
    {
        // Look at every bookshelf and subject, then collapse them into one broad category.
        $labels = array_merge(
            array_map(fn ($shelf) => preg_replace('/^Category:\s*/i', '', $shelf), $item['bookshelves'] ?? []),
            array_map(fn ($subject) => explode(' -- ', $subject)[0], $item['subjects'] ?? [])
        );

        return CategoryMapper::map(
            implode(' | ', $labels),
            ($item['title'] ?? '') . ' ' . $this->description($item)
        );
    }

    private function description(array $item): string
    {
        if (! empty($item['summaries'][0])) {
            return Str::limit($item['summaries'][0], 1500, '...');
        }

        $subjects = array_slice($item['subjects'] ?? [], 0, 3);

        return $subjects
            ? 'Subjects: ' . implode('; ', $subjects)
            : 'A public-domain book from Project Gutenberg.';
    }
}