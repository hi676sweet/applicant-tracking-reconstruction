<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use App\Services\HiringWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    private function application(Request $request, int $id): Application
    {
        return Application::where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    public function move(Request $request, int $id, HiringWorkflow $workflow)
    {
        $application = $this->application($request, $id);
        $workflow->authorize($request->user(), $application);
        $data = $request->validate(['stage' => ['required', Rule::in(Application::STAGES)], 'version' => ['required', 'integer', 'min:0']]);
        $workflow->move($request->user(), $application, $data['stage'], $data['version']);

        return to_route('applications.show', $id)->with('success', 'Application moved. The change is recorded in the audit trail.');
    }

    public function schedule(Request $request, int $id, HiringWorkflow $workflow)
    {
        $application = $this->application($request, $id);
        $workflow->authorize($request->user(), $application);
        $data = $request->validate([
            'interviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)->whereIn('role', ['admin', 'recruiter'])],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);
        $workflow->schedule($request->user(), $application, User::findOrFail($data['interviewer_id']), CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'UTC')->startOfMinute(), CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at'], 'UTC')->startOfMinute());

        return to_route('applications.show', $id)->with('success', 'Interview scheduled. All times are shown in UTC.');
    }

    public function cancel(Request $request, int $id, HiringWorkflow $workflow)
    {
        $interview = Interview::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $workflow->cancelInterview($request->user(), $interview);

        return to_route('applications.show', $interview->application_id)->with('success', 'Interview cancelled.');
    }
}
