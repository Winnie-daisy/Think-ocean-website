<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Database\Factories\Concerns\PublishableFactoryStates;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use PublishableFactoryStates;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(15),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'author_id' => User::factory(),
        ];
    }
}
