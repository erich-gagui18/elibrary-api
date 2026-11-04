<?php

namespace App\Support;

/**
 * Groups Project Gutenberg's very granular bookshelves/subjects
 * (e.g. "Admirals", "Astoria (New York, N.Y.)") into a handful of
 * broad categories that work well as a filter in the web and Android apps.
 */
class CategoryMapper
{
    public const GENERAL = 'General';

    /**
     * Broad category => keywords, matched case-insensitively.
     * Order matters: the first category with a matching keyword wins.
     */
    private const RULES = [
        'Mystery & Crime' => ['mystery', 'detective', 'crime', 'thriller', 'murder'],
        'Science Fiction & Fantasy' => ['science fiction', 'fantasy', 'fairy tale'],
        'Adventure' => ['adventure', 'voyage', 'cowboy', 'ranch', 'trail', 'pirate', 'sea stories'],
        'Poetry & Drama' => ['poetry', 'poem', 'drama', 'plays', 'frost, robert'],
        'Children & Young Adult' => ['children', 'young adult'],
        'Philosophy & Religion' => [
            'philosoph', 'ethic', 'religio', 'spiritual', 'christian',
            'catholic', 'atheis', 'humanis', 'theolog', 'bible',
        ],
        'Science & Nature' => [
            'astronom', 'archaeolog', 'anthropolog', 'heredity', 'physics',
            'biology', 'nature', 'science', 'camera', 'satellite',
        ],
        'History & Biography' => [
            'history', 'biograph', 'world war', 'revolution', 'army',
            'battle', 'admiral', 'ford madox',
        ],
        'Reference' => ['encyclop', 'dictionar', 'reference'],
        'Literature & Fiction' => [
            'literature', 'classics', 'novel', 'fiction', 'best books',
            'banned books', 'bestsellers', 'love stories', 'romance', 'humor',
            'wit and', 'family life', 'betrothal', 'courtesan', 'friendship',
            'fathers and daughters', 'authorship', 'authors,', 'writers',
            'melville', 'farm life',
        ],
        'Travel & Places' => [
            'travel', 'africa', 'china', 'japan', 'egypt', 'russia',
            'sri lanka', 'canada', 'england', 'france', 'french',
            'great britain', 'venice', 'north carolina', 'astoria',
            'river', 'clapperton',
        ],
    ];

    /** Words in a title/description that suggest a story when no category matched. */
    private const FICTION_HINTS = ['novel', 'fiction', 'stories', 'story', 'romance'];

    /**
     * @param  string|null  $primary  category / bookshelf / subject text to classify
     * @param  string  $extra  title + description, used only if $primary matches nothing
     */
    public static function map(?string $primary, string $extra = ''): string
    {
        $primary = mb_strtolower(trim((string) $primary));

        if ($primary !== '') {
            foreach (self::RULES as $broad => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($primary, $keyword)) {
                        return $broad;
                    }
                }
            }
        }

        $extra = mb_strtolower($extra);
        foreach (self::FICTION_HINTS as $hint) {
            if (str_contains($extra, $hint)) {
                return 'Literature & Fiction';
            }
        }

        return self::GENERAL;
    }

    /** All broad category names, handy for tests or dropdowns. */
    public static function all(): array
    {
        return array_merge(array_keys(self::RULES), [self::GENERAL]);
    }
}
