<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MemberController extends Controller
{
    use AuthorizesRequests;
    protected $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Member::class);
        $members = $this->memberService->list($request->all());
        return MemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request)
    {
        $this->authorize('create', Member::class);
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;
        
        $member = $this->memberService->createMember($data);
        return new MemberResource($member->load('customer'));
    }

    public function show(Member $member)
    {
        $this->authorize('view', $member);
        return new MemberResource($member->load('customer', 'surveyor'));
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $this->authorize('update', $member);
        $member = $this->memberService->updateMember($member, $request->validated());
        return new MemberResource($member->load('customer'));
    }

    public function activate(Member $member)
    {
        $this->authorize('activate', $member);
        $member = $this->memberService->activateMember($member);
        return new MemberResource($member->load('customer'));
    }

    public function showDocument(Member $member, $type)
    {
        $this->authorize('viewDocument', $member);
        
        $path = $this->memberService->getDocumentPath($member, $type);
        
        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return response()->file(Storage::disk('local')->path($path));
    }
}
