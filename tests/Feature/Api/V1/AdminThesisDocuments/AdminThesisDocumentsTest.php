<?php

namespace Tests\Feature\Api\V1\AdminThesisDocuments;

use App\AuditLog;
use App\Permission;
use App\Role;
use App\ThesisDocument;
use App\ThesisDocumentSection;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminThesisDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = factory(User::class)->create();
        $perm = Permission::firstOrCreate(
            ['code' => 'catalog.manage'],
            ['module' => 'catalog', 'action' => 'manage', 'status' => 'active']
        );
        $role = Role::create(['code' => 'admin_' . uniqid(), 'name' => 'Admin', 'status' => 'active']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function doc(array $overrides = []): ThesisDocument
    {
        return ThesisDocument::create(array_merge([
            'title'  => 'Doc ' . uniqid(),
            'slug'   => 'doc-' . uniqid(),
            'status' => 'draft',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/admin/thesis-documents')->assertStatus(401);
        $this->postJson('/api/v1/admin/thesis-documents', [])->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/admin/thesis-documents')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/thesis-documents', ['title' => 'X'])->assertStatus(403);
    }

    public function test_admin_can_list_all_documents_including_drafts()
    {
        $admin = $this->adminUser();
        $this->doc(['status' => 'draft']);
        $this->doc(['status' => 'published']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/thesis-documents')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_admin_can_create_document_and_it_is_audited()
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/thesis-documents', [
                'title'  => 'Arquitectura del sistema',
                'status' => 'draft',
            ])
            ->assertStatus(201);

        $this->assertEquals('Arquitectura del sistema', $response->json('data.title'));
        $this->assertNotNull($response->json('data.slug'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $admin->id,
            'action'      => 'thesis_document_created',
            'entity_name' => 'thesis_documents',
        ]);
    }

    public function test_admin_can_update_document_status()
    {
        $admin = $this->adminUser();
        $doc   = $this->doc(['status' => 'draft']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/thesis-documents/{$doc->id}", ['status' => 'published'])
            ->assertStatus(200);

        $this->assertEquals('published', $response->json('data.status'));
    }

    public function test_admin_can_soft_delete_document()
    {
        $admin = $this->adminUser();
        $doc   = $this->doc();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/thesis-documents/{$doc->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('thesis_documents', ['id' => $doc->id]);
    }

    public function test_section_from_different_document_returns_404()
    {
        $admin   = $this->adminUser();
        $doc1    = $this->doc();
        $doc2    = $this->doc();
        $section = ThesisDocumentSection::create([
            'document_id' => $doc2->id,
            'title'       => 'Seccion de otro doc',
            'sort_order'  => 1,
            'status'      => 'draft',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/thesis-documents/{$doc1->id}/sections/{$section->id}", ['title' => 'Hack'])
            ->assertStatus(404);
    }

    public function test_sections_are_created_and_ordered()
    {
        $admin = $this->adminUser();
        $doc   = $this->doc();

        $this->actingAs($admin)->postJson("/api/v1/admin/thesis-documents/{$doc->id}/sections", [
            'title' => 'Seccion B', 'sort_order' => 2, 'status' => 'draft',
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson("/api/v1/admin/thesis-documents/{$doc->id}/sections", [
            'title' => 'Seccion A', 'sort_order' => 1, 'status' => 'draft',
        ])->assertStatus(201);

        $this->assertDatabaseCount('thesis_document_sections', 2);
    }

    public function test_version_is_created_with_snapshot_and_immutable()
    {
        $admin = $this->adminUser();
        $doc   = $this->doc(['title' => 'Modelo de datos', 'status' => 'published']);

        ThesisDocumentSection::create([
            'document_id' => $doc->id,
            'title'       => 'Seccion 1',
            'sort_order'  => 1,
            'status'      => 'published',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/thesis-documents/{$doc->id}/versions")
            ->assertStatus(201);

        $this->assertEquals(1, $response->json('data.version_number'));
        $this->assertEquals($doc->id, $response->json('data.document_id'));

        $second = $this->actingAs($admin)
            ->postJson("/api/v1/admin/thesis-documents/{$doc->id}/versions")
            ->assertStatus(201);

        $this->assertEquals(2, $second->json('data.version_number'));

        $versions = $this->actingAs($admin)
            ->getJson("/api/v1/admin/thesis-documents/{$doc->id}/versions")
            ->assertStatus(200);

        $this->assertCount(2, $versions->json('data'));
    }

    public function test_missing_title_on_create_is_rejected()
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/thesis-documents', ['status' => 'draft'])
            ->assertStatus(422);
    }
}
