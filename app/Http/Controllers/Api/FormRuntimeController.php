<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormBuilder\StoreFormSubmissionRequest;
use App\Http\Requests\FormBuilder\UpdateSubmissionStatusRequest;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormBuilder\Runtime\FormAccessPolicy;
use App\Services\FormBuilder\Runtime\FormRenderService;
use App\Services\FormBuilder\Runtime\FormSubmissionService;
use App\Support\FormBuilder\FormRuntimeApiResponder;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormSubmissionRequest;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FormRuntimeController extends Controller
{
    public function __construct(
        protected FormRenderService $renderService,
        protected FormSubmissionService $submissionService,
        protected FormAccessPolicy $policy,
        protected FormRuntimeApiResponder $responder,
    ) {}

    public function runtime(Request $request, Form $form): JsonResponse
    {
        $context = $this->buildContext($request);

        try {
            $this->policy->authorizeRender($form, $context);
            $runtime = $this->renderService->render($form, $context);

            return $this->responder->runtime($runtime);
        } catch (\Throwable $exception) {
            return $this->responder->fromThrowable($exception);
        }
    }

    public function store(StoreFormSubmissionRequest $request, Form $form): JsonResponse
    {
        $context = $this->buildContext($request);
        $validated = $request->validated();
        $targetStatus = $validated['target_status'] ?? FormSubmissionStatus::SUBMITTED;
        $isDraft = $targetStatus === FormSubmissionStatus::DRAFT;
        $runtime = null;

        try {
            $runtime = $this->renderService->render($form, $context);

            if ($isDraft) {
                $this->authorizeDraft($form, $context, $runtime, $validated['submission_id'] ?? null);
            } else {
                $this->policy->authorizeSubmit(
                    $form,
                    $context,
                    $runtime,
                    $validated['snapshot_hash'] ?? null,
                );
            }

            $result = $this->submissionService->submit(
                $form,
                $runtime,
                new FormSubmissionRequest(
                    data: $validated['data'],
                    locale: $validated['locale'] ?? $context->resolvedLocale(),
                    targetStatus: $targetStatus,
                    snapshotHash: $validated['snapshot_hash'] ?? null,
                    submissionId: $validated['submission_id'] ?? null,
                ),
                $context,
            );

            return $this->responder->submissionCreated($result, $isDraft);
        } catch (\Throwable $exception) {
            return $this->responder->fromThrowable($exception, $runtime);
        }
    }

    public function show(Request $request, Form $form, FormSubmission $submission): JsonResponse
    {
        $context = $this->buildContext($request);

        try {
            $submission = $this->submissionService->findForForm($form, $submission->id);
            $this->policy->authorizeViewSubmission($form, $context, $submission);
            $submission->load('user');

            return $this->responder->submissionDetail($submission);
        } catch (\Throwable $exception) {
            return $this->responder->fromThrowable($exception);
        }
    }

    public function index(Request $request, Form $form): JsonResponse
    {
        $context = $this->buildContext($request);

        try {
            $this->policy->authorizeListSubmissions($form, $context);

            $perPage = min((int) $request->query('per_page', 15), 50);
            $query = FormSubmission::query()
                ->where('form_id', $form->id)
                ->with('user')
                ->latest();

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            if ($search = $request->query('search')) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('status', 'like', "%{$search}%")
                        ->orWhere('workflow_stage', 'like', "%{$search}%");
                });
            }

            if ($from = $request->query('from')) {
                $query->whereDate('created_at', '>=', $from);
            }

            if ($to = $request->query('to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            $paginator = $query->paginate($perPage);

            return $this->responder->submissionList($form, $paginator, $context->resolvedLocale());
        } catch (\Throwable $exception) {
            return $this->responder->fromThrowable($exception);
        }
    }

    public function updateStatus(
        UpdateSubmissionStatusRequest $request,
        Form $form,
        FormSubmission $submission,
    ): JsonResponse {
        $context = $this->buildContext($request);
        $validated = $request->validated();

        try {
            $submission = $this->submissionService->findForForm($form, $submission->id);
            $this->policy->authorizeTransitionStatus(
                $form,
                $context,
                $submission,
                $validated['status'],
            );

            $updated = $this->submissionService->transitionStatus(
                $submission,
                $validated['status'],
                $context,
                $validated['comment'] ?? null,
            );

            return $this->responder->statusUpdated($updated);
        } catch (\Throwable $exception) {
            return $this->responder->fromThrowable($exception);
        }
    }

    protected function authorizeDraft(
        Form $form,
        FormRuntimeContext $context,
        $runtime,
        ?int $submissionId,
    ): void {
        if ($submissionId) {
            $submission = $this->submissionService->findForForm($form, $submissionId);
            $this->policy->authorizeUpdateDraft($form, $context, $submission, $runtime);

            return;
        }

        $this->policy->authorizeSaveDraft($form, $context, $runtime);
    }

    protected function buildContext(Request $request): FormRuntimeContext
    {
        $locale = $this->resolveLocale($request);
        $user = Auth::guard('sanctum')->user() ?? $request->user();

        if ($user) {
            $context = FormRuntimeContext::forUser($user, $locale);
            $context->ipAddress = $request->ip();

            return $context;
        }

        return FormRuntimeContext::anonymous($locale, $request->ip());
    }

    protected function resolveLocale(Request $request): string
    {
        $locale = $request->query('locale')
            ?? $request->input('locale')
            ?? $request->header('Accept-Language', 'ar');

        if (is_string($locale) && str_contains($locale, ',')) {
            $locale = trim(explode(',', $locale)[0]);
        }

        if (is_string($locale) && str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
