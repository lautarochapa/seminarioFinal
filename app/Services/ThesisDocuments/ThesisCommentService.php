<?php

namespace App\Services\ThesisDocuments;

use App\AuditLog;
use App\Repositories\ThesisDocuments\ThesisCommentRepository;
use App\Repositories\ThesisDocuments\ThesisDocumentRepository;
use App\ThesisComment;

class ThesisCommentService
{
    private $commentRepo;
    private $documentRepo;

    public function __construct(ThesisCommentRepository $commentRepo, ThesisDocumentRepository $documentRepo)
    {
        $this->commentRepo  = $commentRepo;
        $this->documentRepo = $documentRepo;
    }

    public function list(int $documentId, array $filters): array
    {
        $this->documentRepo->findPublished($documentId);

        $paginator = $this->commentRepo->paginate($documentId, $filters);

        return [
            'data'  => $paginator->map([$this, 'formatComment'])->values()->toArray(),
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

    public function create(int $documentId, int $userId, string $comment, string $ip, string $userAgent): ThesisComment
    {
        $this->documentRepo->findPublished($documentId);

        $record = $this->commentRepo->create($documentId, $userId, $comment);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'thesis_comment_created',
            'entity_name' => 'thesis_comments',
            'entity_id'   => $record->id,
            'new_values'  => ['document_id' => $documentId],
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);

        $record->load('user:id,name');

        return $record;
    }

    public function formatComment(ThesisComment $c): array
    {
        return [
            'id'         => $c->id,
            'comment'    => $c->comment,
            'status'     => $c->status,
            'author'     => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
            'created_at' => $c->created_at ? $c->created_at->toIso8601String() : null,
        ];
    }
}
