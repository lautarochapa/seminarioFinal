<?php

namespace Tests\Feature\Api\V1\ThesisDocuments;

use App\Permission;
use App\Role;
use App\ThesisDocument;
use App\ThesisDocumentSection;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThesisDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(): User
    {
        $user = factory(User::class)->create();
        $perm = Permission::firstOrCreate(
            ['code' => 'thesis_documents.read'],
            ['module' => 'thesis_documents', 'action' => 'read', 'status' => 'active']
        );
        $role = Role::create(['code' => 'role_' . uniqid(), 'name' => 'Reader', 'status' => 'active']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function publishedDoc(array $overrides = []): ThesisDocument
    {
        return ThesisDocument::create(array_merge([
            'title'  => 'Documento ' . uniqid(),
            'slug'   => 'doc-' . uniqid(),
            'status' => 'published',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/thesis-documents')->assertStatus(401);
        $this->getJson('/api/v1/thesis-documents/1')->assertStatus(401);
        $this->getJson('/api/v1/thesis-documents/1/sections')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/thesis-documents')->assertStatus(403);
    }

    public function test_list_returns_only_published_documents()
    {
        $user = $this->userWithPermission();

        $this->publishedDoc(['title' => 'Doc Publicado', 'status' => 'published']);
        $this->publishedDoc(['title' => 'Doc Activo',    'status' => 'active']);
        $this->publishedDoc(['title' => 'Doc Borrador',  'status' => 'draft']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/thesis-documents')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertArrayHasKey('trace_id', $response->json());

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertNotContains('Doc Borrador', $titles);
    }

    public function test_list_filters_by_title()
    {
        $user = $this->userWithPermission();
        $this->publishedDoc(['title' => 'Arquitectura del sistema']);
        $this->publishedDoc(['title' => 'Casos de uso']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/thesis-documents?title=Arquitectura')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertStringContainsString('Arquitectura', $response->json('data.0.title'));
    }

    public function test_show_returns_document_with_metadata()
    {
        $user = $this->userWithPermission();
        $doc  = $this->publishedDoc(['title' => 'Modelo de datos', 'description' => 'Descripcion del modelo']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}")
            ->assertStatus(200);

        $this->assertEquals($doc->id,    $response->json('data.id'));
        $this->assertEquals('Modelo de datos', $response->json('data.title'));
        $this->assertEquals('Descripcion del modelo', $response->json('data.description'));
        $this->assertArrayHasKey('slug',       $response->json('data'));
        $this->assertArrayHasKey('created_at', $response->json('data'));
    }

    public function test_show_returns_404_for_draft_document()
    {
        $user = $this->userWithPermission();
        $doc  = $this->publishedDoc(['status' => 'draft']);

        $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}")
            ->assertStatus(404);
    }

    public function test_sections_are_ordered_by_sort_order()
    {
        $user = $this->userWithPermission();
        $doc  = $this->publishedDoc();

        ThesisDocumentSection::create(['document_id' => $doc->id, 'title' => 'Seccion C', 'sort_order' => 3, 'status' => 'published']);
        ThesisDocumentSection::create(['document_id' => $doc->id, 'title' => 'Seccion A', 'sort_order' => 1, 'status' => 'published']);
        ThesisDocumentSection::create(['document_id' => $doc->id, 'title' => 'Seccion B', 'sort_order' => 2, 'status' => 'published']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}/sections")
            ->assertStatus(200);

        $titles = collect($response->json('data.sections'))->pluck('title')->toArray();
        $this->assertEquals(['Seccion A', 'Seccion B', 'Seccion C'], $titles);
        $this->assertEquals($doc->id, $response->json('data.document_id'));
    }

    public function test_sections_strip_html_from_content()
    {
        $user = $this->userWithPermission();
        $doc  = $this->publishedDoc();

        ThesisDocumentSection::create([
            'document_id' => $doc->id,
            'title'       => 'Intro',
            'content'     => '<p>Contenido <strong>importante</strong></p>',
            'sort_order'  => 1,
            'status'      => 'published',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/thesis-documents/{$doc->id}/sections")
            ->assertStatus(200);

        $content = $response->json('data.sections.0.content');
        $this->assertStringNotContainsString('<p>', $content);
        $this->assertStringContainsString('Contenido', $content);
    }
}
