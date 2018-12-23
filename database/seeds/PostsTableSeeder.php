<?php

use App\Bangumi;
use App\Post;
use Illuminate\Database\Seeder;

class PostsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        // for ($i = 0; $i < 100; $i++) {
        //     $posts = factory(App\Post::class, 1000);
        //     $posts->create();
        // }
        $posts = factory(Post::class, 10);
        $posts->create()->each(function ($post) {
            $post->bangumi()->save(factory(Bangumi::class)->make());
        });
    }
}
