<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Database\Factories\Concerns\PublishableFactoryStates;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    use PublishableFactoryStates;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(15),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'author_id' => User::factory(),
        ];
    }
}
