<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return ['type' => Category::TYPE_POST, 'name' => $name, 'slug' => Str::slug($name)];
    }

    public function forProjects(): static
    {
        return $this->state(fn () => ['type' => Category::TYPE_PROJECT]);
    }
}
