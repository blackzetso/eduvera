<?php
$file    = 'resources/js/Pages/Student/Theme1/LiveStreamDetail.vue';
$content = file_get_contents($file);

// Find the COMMENT section start
$start = strpos($content, "activeTab === 'comment'");
// Back up to the opening <!-- comment
$blockStart = strrpos($content, '<!-- ', $start);

// Find the closing </div> of the comment block — it's the div v-show
// Count div opens/closes from the v-show div
$divOpen = strpos($content, '<div v-show="activeTab', $blockStart);
$pos = $divOpen;
$depth = 0;
$len = strlen($content);
while ($pos < $len) {
    if (substr($content, $pos, 4) === '<div') { $depth++; $pos += 4; }
    elseif (substr($content, $pos, 6) === '</div>') {
        $depth--;
        if ($depth === 0) { $blockEnd = $pos + 6; break; }
        $pos += 6;
    } else { $pos++; }
}

echo "Start=$blockStart End=$blockEnd\n";
echo "BEFORE: " . substr($content, $blockStart, 80) . "\n";
echo "AFTER: " . substr($content, $blockEnd - 10, 30) . "\n";

$newBlock = <<<'VUE'
<!-- Comment Tab -->
                                <div v-show="activeTab === 'comment'">
                                    <h5 class="mb-4">Ask Your Question</h5>

                                    <!-- Post a comment form -->
                                    <div class="d-flex mb-4">
                                        <div class="me-3 flex-shrink-0">
                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                                style="width:44px;height:44px;">
                                                <i class="bi bi-person-fill text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div v-if="commentError" class="alert alert-danger py-2 mb-2 small">{{ commentError }}</div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-sm-6">
                                                    <input v-model="commentForm.author_name" type="text" class="form-control form-control-sm" placeholder="Your name *" required>
                                                </div>
                                                <div class="col-sm-6">
                                                    <input v-model="commentForm.author_email" type="email" class="form-control form-control-sm" placeholder="Email (optional)">
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <textarea v-model="commentForm.body" class="form-control" rows="2" placeholder="Add a comment or ask a question..."></textarea>
                                                <div class="align-self-end">
                                                    <button class="btn btn-primary mb-0" @click="submitComment" :disabled="commentSubmitting">
                                                        <span v-if="commentSubmitting"><span class="spinner-border spinner-border-sm"></span></span>
                                                        <span v-else>Post</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Loading -->
                                    <div v-if="commentsLoading" class="text-center py-3 text-muted">
                                        <div class="spinner-border spinner-border-sm me-2"></div> Loading comments...
                                    </div>

                                    <!-- Empty state -->
                                    <div v-else-if="!comments.length" class="text-center py-4 text-muted">
                                        <i class="fas fa-comment-dots fa-3x mb-3 opacity-25 d-block"></i>
                                        <p class="mb-0">No comments yet. Be the first to ask a question.</p>
                                    </div>

                                    <!-- Comments list -->
                                    <div v-else>
                                        <div v-for="comment in comments" :key="comment.id" class="mb-4">
                                            <!-- Comment -->
                                            <div class="d-flex">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                        style="width:40px;height:40px;">
                                                        <i class="bi bi-person-fill text-secondary"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="bg-light rounded-3 p-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="fw-semibold small">{{ comment.author_name }}</span>
                                                            <span class="text-muted" style="font-size:11px;">{{ comment.created_at }}</span>
                                                        </div>
                                                        <p class="mb-0 small">{{ comment.body }}</p>
                                                    </div>
                                                    <button class="btn btn-link btn-sm px-1 text-muted" @click="toggleReply(comment.id)">
                                                        <i class="bi bi-reply me-1"></i>Reply
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Replies -->
                                            <div v-if="comment.replies && comment.replies.length" class="ms-5 mt-2">
                                                <div v-for="reply in comment.replies" :key="reply.id" class="d-flex mb-2">
                                                    <div class="me-2 flex-shrink-0">
                                                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                                            style="width:32px;height:32px;">
                                                            <i class="bi bi-person-fill text-primary" style="font-size:14px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="bg-white border rounded-3 p-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="fw-semibold" style="font-size:12px;">{{ reply.author_name }}</span>
                                                                <span class="text-muted" style="font-size:11px;">{{ reply.created_at }}</span>
                                                            </div>
                                                            <p class="mb-0" style="font-size:13px;">{{ reply.body }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reply form -->
                                            <div v-if="replyOpen[comment.id]" class="ms-5 mt-2">
                                                <div class="d-flex gap-2">
                                                    <input v-model="replyForms[comment.id].author_name" type="text" class="form-control form-control-sm" placeholder="Your name *" style="max-width:160px;">
                                                    <input v-model="replyForms[comment.id].body" type="text" class="form-control form-control-sm" placeholder="Write a reply...">
                                                    <button class="btn btn-sm btn-primary flex-shrink-0" @click="submitReply(comment.id)" :disabled="replySubmitting[comment.id]">
                                                        <span v-if="replySubmitting[comment.id]"><span class="spinner-border spinner-border-sm"></span></span>
                                                        <span v-else>Send</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary flex-shrink-0" @click="replyOpen[comment.id] = false">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
VUE;

$content = substr($content, 0, $blockStart) . $newBlock . substr($content, $blockEnd);
file_put_contents($file, $content);
echo "Done. Size=" . strlen($content) . "\n";
