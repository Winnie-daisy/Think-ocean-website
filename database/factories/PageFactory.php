<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\User;
use Database\Factories\Concerns\PublishableFactoryStates;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    use PublishableFactoryStates;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'intro' => fake()->sentence(12),
            'sections' => [
                ['type' => 'rich_text', 'data' => ['heading' => fake()->sentence(4), 'body' => '<p>'.fake()->paragraph().'</p>']],
            ],
            'author_id' => User::factory(),
        ];
    }
}
