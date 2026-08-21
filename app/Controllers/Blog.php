<?php

namespace App\Controllers;

/**
 * Blog Controller
 *
 * Handles blog listing and single post display
 * Uses Cockpit CMS 'posts' collection
 *
 * @package App\Controllers
 */
class Blog extends WebController
{
    /**
     * Display all published blog posts
     *
     * @return string
     */
    public function index(): string
    {
        // Fetch published posts from Cockpit
        $posts = $this->cockpit->getCollectionCached('posts', [
            'filter' => [
                'published' => true,
            ],
            'sort' => [
                'published_at' => -1,
            ],
        ]);

        $data = [
            'title' => 'Blog',
            'posts' => $posts,
        ];

        return $this->render('blog.index', $data);
    }

    /**
     * Display a single blog post
     *
     * @param string $id Post ID
     * @return string
     */
    public function show(string $id): string
    {
        // Fetch single post by ID
        $post = $this->cockpit->getItemCached('posts', $id);

        // Check if post exists and is published
        if ($post === null || !isset($post['published']) || !$post['published']) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Blog post not found');
        }

        $data = [
            'title' => $post['title'] ?? 'Blog Post',
            'post' => $post,
        ];

        return $this->render('blog.show', $data);
    }
}
