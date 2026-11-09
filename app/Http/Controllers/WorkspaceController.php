<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\HiringWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function application(Request $request, int $id)
    {
        $application = Application::where('organization_id', $request->user()->organization_id)->with(['candidate', 'jobOpening', 'interviews.interviewer', 'activityLogs.actor'])->findOrFail($id);
        $interviewers = User::where('organization_id', $request->user()->organization_id)->whereIn('role', ['admin', 'recruiter'])->orderBy('name')->get();

        return view('applications.show', compact('application', 'interviewers'));
    }

    public function createApplication(Request $request, HiringWorkflow $workflow)
    {
        $workflow->authorize($request->user());
        $jobs = JobOpening::where('organization_id', $request->user()->organization_id)->where('status', 'open')->orderBy('title')->get();

        return view('applications.create', compact('jobs'));
    }

    public function storeApplication(Request $request, HiringWorkflow $workflow)
    {
        $workflow->authorize($request->user());
        $data = $request->validate([
            'job_opening_id' => ['required', 'integer', Rule::exists('job_openings', 'id')->where('organization_id', $request->user()->organization_id)],
            'name' => ['required', 'string', 'min:2', 'max:100'], 'email' => ['required', 'email', 'max:150', 'ends_with:.test'],
            'headline' => ['nullable', 'string', 'max:160'],
        ], ['email.ends_with' => 'Use a fictional .test email for this demo.']);
        $application = $workflow->addApplication($request->user(), JobOpening::findOrFail($data['job_opening_id']), $data);

        return to_route('applications.show', $application->id)->with('success', 'Application added to the pipeline.');
    }

    public function jobs(Request $request)
    {
        $jobs = JobOpening::where('organization_id', $request->user()->organization_id)->withCount('applications')->latest()->get();

        return view('jobs.index', compact('jobs'));
    }

    public function createJob(Request $request, HiringWorkflow $workflow)
    {
        $workflow->authorize($request->user());

        return view('jobs.create');
    }

    public function storeJob(Request $request, HiringWorkflow $workflow)
    {
        $workflow->authorize($request->user());
        $data = $request->validate(['title' => ['required', 'string', 'min:3', 'max:100'], 'department' => ['required', 'string', 'max:80'], 'location' => ['required', 'string', 'max:100'], 'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract'])]]);
        $job = DB::transaction(function () use ($request, $workflow, $data): JobOpening {
            $job = JobOpening::create($data + ['organization_id' => $request->user()->organization_id]);
            $workflow->record($request->user(), 'job.created', 'Opened role: '.$job->title.'.');

            return $job;
        });

        return to_route('pipeline', ['job' => $job->id])->with('success', 'New role opened. Add your first application.');
    }

    public function activity(Request $request)
    {
        $events = ActivityLog::where('organization_id', $request->user()->organization_id)->with('actor')->latest('id')->paginate(25);

        return view('activity', compact('events'));
    }
}
