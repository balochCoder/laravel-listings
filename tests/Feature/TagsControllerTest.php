<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TagsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     *
     * @test
     */
    public function itListTags()
    {
        $response = $this->get('/api/tags');

      

        $response->assertOk();
        $this->assertNotNull($response->json('data')[0]['id']);
    }
}
