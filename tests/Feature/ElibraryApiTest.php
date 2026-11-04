<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElibraryApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooks(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Book::create([
                'gutenberg_id' => 1000 + $i,
                'title'        => "Book {$i}",
                'author'       => 'Test Author',
                'category'     => 'Fiction',
                'file_url'     => "https://example.com/{$i}.epub",
            ]);
        }
    }

    public function test_user_can_register_login_and_logout(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Erich', 'email' => 'erich@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]);
        $register->assertCreated()->assertJsonStructure(['token', 'user']);

        $login = $this->postJson('/api/login', [
            'email' => 'erich@example.com', 'password' => 'password123',
        ]);
        $login->assertOk();

        $this->withToken($login->json('token'))
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'password123']);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'nope'])
            ->assertStatus(401);
    }

    public function test_books_can_be_searched_and_filtered(): void
    {
        $this->makeBooks(3);
        Book::create([
            'gutenberg_id' => 9999, 'title' => 'Dracula', 'author' => 'Bram Stoker',
            'category' => 'Horror', 'file_url' => 'https://example.com/d.epub',
        ]);

        $this->getJson('/api/books?q=Dracula')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/books?category=Horror')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_is_blocked_after_five_downloads(): void
    {
        $this->makeBooks(6);
        $token = $this->postJson('/api/guest/start')->json('token');

        foreach (Book::limit(5)->get() as $book) {
            $this->withHeader('X-Guest-Token', $token)
                ->getJson("/api/books/{$book->id}/download")->assertOk();
        }

        $sixth = Book::orderByDesc('id')->first();
        $this->withHeader('X-Guest-Token', $token)
            ->getJson("/api/books/{$sixth->id}/download")
            ->assertStatus(429)
            ->assertJson(['remaining' => 0]);
    }

    public function test_member_is_blocked_after_ten_downloads(): void
    {
        $this->makeBooks(11);
        $user = User::factory()->create();

        foreach (Book::limit(10)->get() as $book) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/books/{$book->id}/download")->assertOk();
        }

        $last = Book::orderByDesc('id')->first();
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$last->id}/download")
            ->assertStatus(429);
    }

    public function test_download_requires_guest_session_or_login(): void
    {
        $this->makeBooks(1);
        $this->getJson('/api/books/1/download')->assertStatus(401);
    }
}
