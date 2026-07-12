<?php

namespace App\Repositories\ThesisDocuments;

use App\ThesisDocument;
use App\ThesisDocumentSection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class ThesisDocumentRepository
{
    const VISIBLE_STATUSES = ['published', 'active'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ThesisDocument::whereIn('status', self::VISIBLE_STATUSES)
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderBy('title');

        if (!empty($filters['title'])) {
            $query->where('title', 'ILIKE', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::VISIBLE_STATUSES)) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['slug_prefix'])) {
            $query->where('slug', 'ILIKE', $filters['slug_prefix'] . '%');
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findPublished(int $id): ThesisDocument
    {
        $doc = ThesisDocument::whereIn('status', self::VISIBLE_STATUSES)->find($id);

        if (!$doc) {
            throw (new ModelNotFoundException)->setModel(ThesisDocument::class, $id);
        }

        return $doc;
    }

    public function sectionsForDocument(int $documentId): array
    {
        $sections = ThesisDocumentSection::where('document_id', $documentId)
            ->where('status', '!=', 'draft')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'document_id', 'parent_id', 'title', 'content', 'sort_order', 'status']);

        return $this->buildTree($sections);
    }

    private function buildTree($sections, int $parentId = null): array
    {
        $tree = [];

        foreach ($sections as $section) {
            if ($section->parent_id === $parentId) {
                $node             = $section->toArray();
                $node['content']  = $section->content ? strip_tags($section->content) : null;
                $node['children'] = $this->buildTree($sections, $section->id);
                $tree[]           = $node;
            }
        }

        return $tree;
    }
}
