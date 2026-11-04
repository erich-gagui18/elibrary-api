<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportGutenbergBooks extends Command
{
    protected $signature = 'books:import {--count=220 : Total books to have in the catalog}';

    protected $description = 'Import public-domain books (metadata + EPUB links) from Project Gutenberg via Gutendex';

    public function handle(): int
    {
        $target = (int) $this->option('count');
        $url = 'https://gutendex.com/books?languages=en&mime_type=application%2Fepub';

        $this->info("Importing until the catalog has {$target} books...");

        while ($url && Book::count() < $target) {
            $response = Http::timeout(30)->retry(3, 1500)->get($url);

            if ($response->failed()) {
                $this->error("Gutendex request failed: HTTP {$response->status()}");
                return self::FAILURE;
            }

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

            $this->line('  catalog size: ' . Book::count());
            $url = $response->json('next');
        }

        $this->info('Done. Total books: ' . Book::count());

        return self::SUCCESS;
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
        $shelf = $item['bookshelves'][0] ?? null;
        if ($shelf) {
            return Str::limit(trim(preg_replace('/^Category:\s*/i', '', $shelf)), 110, '');
        }

        $subject = $item['subjects'][0] ?? null;
        if ($subject) {
            return Str::limit(trim(explode(' -- ', $subject)[0]), 110, '');
        }

        return 'General';
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
