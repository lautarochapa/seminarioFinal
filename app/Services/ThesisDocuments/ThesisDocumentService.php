<?php

namespace App\Services\ThesisDocuments;

use App\Repositories\ThesisDocuments\ThesisDocumentRepository;

class ThesisDocumentService
{
    private $repo;

    public function __construct(ThesisDocumentRepository $repo)
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
        $doc = $this->repo->findPublished($id);

        return $this->formatDocument($doc);
    }

    public function sections(int $id): array
    {
        $doc  = $this->repo->findPublished($id);
        $tree = $this->repo->sectionsForDocument($doc->id);

        return [
            'document_id'    => $doc->id,
            'document_title' => $doc->title,
            'sections'       => $tree,
        ];
    }

    public function formatDocument($doc): array
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
        ];
    }
}
