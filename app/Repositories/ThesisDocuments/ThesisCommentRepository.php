<?php

namespace App\Repositories\ThesisDocuments;

use App\ThesisComment;
use Illuminate\Pagination\LengthAwarePaginator;

class ThesisCommentRepository
{
    public function paginate(int $documentId, array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        return ThesisComment::where('document_id', $documentId)
            ->with(['user:id,name'])
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(int $documentId, int $userId, string $comment): ThesisComment
    {
        return ThesisComment::create([
            'document_id' => $documentId,
            'user_id'     => $userId,
            'comment'     => strip_tags($comment),
            'status'      => 'open',
        ]);
    }
}
