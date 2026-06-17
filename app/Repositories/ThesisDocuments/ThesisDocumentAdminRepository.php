<?php

namespace App\Repositories\ThesisDocuments;

use App\ThesisDocument;
use App\ThesisDocumentSection;
use App\ThesisDocumentVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThesisDocumentAdminRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $query = ThesisDocument::withTrashed()
            ->with(['creator:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['title'])) {
            $query->where('title', 'ILIKE', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['deleted']) && $filters['deleted'] === 'true') {
            $query->whereNotNull('deleted_at');
        } elseif (!isset($filters['deleted'])) {
            $query->whereNull('deleted_at');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findOrFail(int $id): ThesisDocument
    {
        $doc = ThesisDocument::find($id);
        if (!$doc) {
            throw (new ModelNotFoundException)->setModel(ThesisDocument::class, $id);
        }
        return $doc;
    }

    public function create(array $data, int $userId): ThesisDocument
    {
        return ThesisDocument::create([
            'title'       => $data['title'],
            'slug'        => $data['slug'] ?? Str::slug($data['title']) . '-' . uniqid(),
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'draft',
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]);
    }

    public function update(ThesisDocument $doc, array $data, int $userId): void
    {
        $fillable = array_filter([
            'title'       => $data['title']       ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'status'      => $data['status']       ?? null,
            'updated_by'  => $userId,
        ], function ($v, $k) use ($data) {
            return $k === 'updated_by' || array_key_exists($k, $data);
        }, ARRAY_FILTER_USE_BOTH);

        if (isset($data['slug'])) {
            $fillable['slug'] = $data['slug'];
        }

        $doc->fill($fillable);
        $doc->save();
    }

    public function softDelete(ThesisDocument $doc): void
    {
        $doc->delete();
    }

    public function findSectionForDocument(int $documentId, int $sectionId): ThesisDocumentSection
    {
        $section = ThesisDocumentSection::where('document_id', $documentId)->find($sectionId);
        if (!$section) {
            throw (new ModelNotFoundException)->setModel(ThesisDocumentSection::class, $sectionId);
        }
        return $section;
    }

    public function createSection(int $documentId, array $data): ThesisDocumentSection
    {
        return ThesisDocumentSection::create([
            'document_id' => $documentId,
            'parent_id'   => $data['parent_id']  ?? null,
            'title'       => $data['title'],
            'content'     => isset($data['content']) ? strip_tags($data['content']) : null,
            'sort_order'  => $data['sort_order']  ?? 0,
            'status'      => $data['status']      ?? 'draft',
        ]);
    }

    public function updateSection(ThesisDocumentSection $section, array $data): void
    {
        $allowed = ['title', 'content', 'sort_order', 'status', 'parent_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $section->{$field} = ($field === 'content' && $data[$field] !== null)
                    ? strip_tags($data[$field])
                    : $data[$field];
            }
        }
        $section->save();
    }

    public function softDeleteSection(ThesisDocumentSection $section): void
    {
        $section->delete();
    }

    public function versionsForDocument(int $documentId): array
    {
        return ThesisDocumentVersion::where('document_id', $documentId)
            ->with(['creator:id,name'])
            ->orderByDesc('version_number')
            ->get(['id', 'document_id', 'version_number', 'created_by', 'created_at'])
            ->toArray();
    }

    public function createVersion(ThesisDocument $doc, int $userId): ThesisDocumentVersion
    {
        $nextNumber = (int) DB::table('thesis_document_versions')
            ->where('document_id', $doc->id)
            ->max('version_number') + 1;

        $sections = ThesisDocumentSection::where('document_id', $doc->id)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'title', 'content', 'sort_order', 'status'])
            ->toArray();

        $snapshot = [
            'title'       => $doc->title,
            'slug'        => $doc->slug,
            'description' => $doc->description,
            'status'      => $doc->status,
            'sections'    => $sections,
        ];

        return ThesisDocumentVersion::create([
            'document_id'      => $doc->id,
            'version_number'   => $nextNumber,
            'content_snapshot' => $snapshot,
            'created_by'       => $userId,
            'created_at'       => now(),
        ]);
    }
}
