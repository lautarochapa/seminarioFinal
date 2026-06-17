<?php

namespace App\Services\ThesisDocuments;

use App\AuditLog;
use App\Repositories\ThesisDocuments\ThesisDocumentAdminRepository;
use App\ThesisDocument;
use App\ThesisDocumentSection;
use App\ThesisDocumentVersion;

class ThesisDocumentAdminService
{
    private $repo;

    public function __construct(ThesisDocumentAdminRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters): array
    {
        $paginator = $this->repo->paginate($filters);

        return [
            'data'  => $paginator->map([$this, 'formatDocument'])->values()->toArray(),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ];
    }

    public function show(int $id): array
    {
        $doc = $this->repo->findOrFail($id);
        $doc->load(['creator:id,name', 'updater:id,name']);
        return $this->formatDocument($doc);
    }

    public function create(array $data, int $userId, string $ip, string $ua): array
    {
        $doc = $this->repo->create($data, $userId);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_document_created',
            'entity_name' => 'thesis_documents',
            'entity_id'   => $doc->id,
            'new_values'  => ['title' => $doc->title, 'status' => $doc->status],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->formatDocument($doc);
    }

    public function update(int $id, array $data, int $userId, string $ip, string $ua): array
    {
        $doc    = $this->repo->findOrFail($id);
        $before = ['title' => $doc->title, 'status' => $doc->status];

        $this->repo->update($doc, $data, $userId);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_document_updated',
            'entity_name' => 'thesis_documents',
            'entity_id'   => $doc->id,
            'old_values'  => $before,
            'new_values'  => ['title' => $doc->title, 'status' => $doc->status],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->formatDocument($doc->fresh());
    }

    public function delete(int $id, int $userId, string $ip, string $ua): void
    {
        $doc = $this->repo->findOrFail($id);

        $this->repo->softDelete($doc);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_document_deleted',
            'entity_name' => 'thesis_documents',
            'entity_id'   => $id,
            'old_values'  => ['title' => $doc->title],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);
    }

    public function createSection(int $docId, array $data, int $userId, string $ip, string $ua): array
    {
        $this->repo->findOrFail($docId);
        $section = $this->repo->createSection($docId, $data);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_section_created',
            'entity_name' => 'thesis_document_sections',
            'entity_id'   => $section->id,
            'new_values'  => ['document_id' => $docId, 'title' => $section->title],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->formatSection($section);
    }

    public function updateSection(int $docId, int $sectionId, array $data, int $userId, string $ip, string $ua): array
    {
        $this->repo->findOrFail($docId);
        $section = $this->repo->findSectionForDocument($docId, $sectionId);
        $before  = ['title' => $section->title, 'sort_order' => $section->sort_order];

        $this->repo->updateSection($section, $data);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_section_updated',
            'entity_name' => 'thesis_document_sections',
            'entity_id'   => $section->id,
            'old_values'  => $before,
            'new_values'  => ['title' => $section->title, 'sort_order' => $section->sort_order],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->formatSection($section->fresh());
    }

    public function deleteSection(int $docId, int $sectionId, int $userId, string $ip, string $ua): void
    {
        $this->repo->findOrFail($docId);
        $section = $this->repo->findSectionForDocument($docId, $sectionId);

        $this->repo->softDeleteSection($section);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_section_deleted',
            'entity_name' => 'thesis_document_sections',
            'entity_id'   => $sectionId,
            'old_values'  => ['document_id' => $docId, 'title' => $section->title],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);
    }

    public function listVersions(int $docId): array
    {
        $this->repo->findOrFail($docId);
        return $this->repo->versionsForDocument($docId);
    }

    public function createVersion(int $docId, int $userId, string $ip, string $ua): array
    {
        $doc     = $this->repo->findOrFail($docId);
        $version = $this->repo->createVersion($doc, $userId);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_version_created',
            'entity_name' => 'thesis_document_versions',
            'entity_id'   => $version->id,
            'new_values'  => ['document_id' => $docId, 'version_number' => $version->version_number],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->formatVersion($version);
    }

    public function formatDocument(ThesisDocument $doc): array
    {
        return [
            'id'          => $doc->id,
            'title'       => $doc->title,
            'slug'        => $doc->slug,
            'description' => $doc->description,
            'status'      => $doc->status,
            'created_by'  => $doc->creator ? ['id' => $doc->creator->id, 'name' => $doc->creator->name] : null,
            'created_at'  => $doc->created_at ? $doc->created_at->toIso8601String() : null,
            'updated_at'  => $doc->updated_at ? $doc->updated_at->toIso8601String() : null,
            'deleted_at'  => $doc->deleted_at ? $doc->deleted_at->toIso8601String() : null,
        ];
    }

    public function formatSection(ThesisDocumentSection $s): array
    {
        return [
            'id'          => $s->id,
            'document_id' => $s->document_id,
            'parent_id'   => $s->parent_id,
            'title'       => $s->title,
            'content'     => $s->content,
            'sort_order'  => $s->sort_order,
            'status'      => $s->status,
            'created_at'  => $s->created_at ? $s->created_at->toIso8601String() : null,
        ];
    }

    public function formatVersion(ThesisDocumentVersion $v): array
    {
        return [
            'id'             => $v->id,
            'document_id'    => $v->document_id,
            'version_number' => $v->version_number,
            'created_by'     => $v->creator ? ['id' => $v->creator->id, 'name' => $v->creator->name] : null,
            'created_at'     => $v->created_at ? $v->created_at->toIso8601String() : null,
        ];
    }
}
