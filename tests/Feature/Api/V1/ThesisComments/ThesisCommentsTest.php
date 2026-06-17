<?php

namespace Tests\Feature\Api\V1\ThesisComments;

use App\AuditLog;
use App\Permission;
use App\Role;
use App\ThesisComment;
use App\ThesisDocument;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThesisCommentsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permCodes): User
    {
        $user = factory(User::class)->create();
        $role = Role::create(['code' => 'role_' . uniqid(), 'name' => 'Role', 'status' => 'active']);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);

        foreach ($permCodes as $code) {
            $perm = Permission::firstOrCreate(
                ['code' => $code],
                ['module' => 'thesis', 'action' => 'read', 'status' => 'active']
            );
            DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id]);
        }

        return $user;
    }

    private function publishedDoc(): ThesisDocument
    {
        return ThesisDocument::create([
            'title'  => 'Doc ' . uniqid(),
            'slug'   => 'doc-' . uniqid(),
            'status' => 'published',
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/thesis-documents/1/comments')->assertStatus(401);
        $this->postJson('/api/v1/thesis-documents/1/comments', [])->assertStatus(401);
    }

    public function test_user_without_read_permission_cannot_list_comments()
    {
        $user = factory(User::class)->create();
        $doc  = $this->publishedDoc();

        $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}/comments")
            ->assertStatus(403);
    }

    public function test_user_with_only_read_permission_cannot_post_comment()
    {
        $user = $this->userWithPermissions(['thesis_documents.read']);
        $doc  = $this->publishedDoc();

        $this->actingAs($user)
            ->postJson("/api/v1/thesis-documents/{$doc->id}/comments", ['comment' => 'Hola'])
            ->assertStatus(403);
    }

    public function test_authorized_user_can_list_comments_paginated()
    {
        $user = $this->userWithPermissions(['thesis_documents.read']);
        $doc  = $this->publishedDoc();

        ThesisComment::create(['document_id' => $doc->id, 'user_id' => $user->id, 'comment' => 'Primer comentario', 'status' => 'open']);
        ThesisComment::create(['document_id' => $doc->id, 'user_id' => $user->id, 'comment' => 'Segundo comentario', 'status' => 'open']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}/comments")
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertArrayHasKey('author', $response->json('data.0'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_teacher_can_post_comment_and_it_is_audited()
    {
        $teacher = $this->userWithPermissions(['thesis_documents.read', 'thesis_comments.write']);
        $doc     = $this->publishedDoc();

        $response = $this->actingAs($teacher)
            ->postJson("/api/v1/thesis-documents/{$doc->id}/comments", [
                'comment' => 'Excelente estructura del modelo',
            ])
            ->assertStatus(201);

        $this->assertEquals('Excelente estructura del modelo', $response->json('data.comment'));
        $this->assertEquals($teacher->id, $response->json('data.author.id'));
        $this->assertNotNull($response->json('data.created_at'));

        $this->assertDatabaseHas('thesis_comments', [
            'document_id' => $doc->id,
            'user_id'     => $teacher->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $teacher->id,
            'action'      => 'thesis_comment_created',
            'entity_name' => 'thesis_comments',
        ]);
    }

    public function test_comment_on_nonexistent_document_returns_404()
    {
        $teacher = $this->userWithPermissions(['thesis_comments.write']);

        $this->actingAs($teacher)
            ->postJson('/api/v1/thesis-documents/99999/comments', ['comment' => 'Algo'])
            ->assertStatus(404);
    }

    public function test_empty_comment_is_rejected()
    {
        $teacher = $this->userWithPermissions(['thesis_documents.read', 'thesis_comments.write']);
        $doc     = $this->publishedDoc();

        $this->actingAs($teacher)
            ->postJson("/api/v1/thesis-documents/{$doc->id}/comments", ['comment' => ''])
            ->assertStatus(422);
    }
}
