<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class VtigerProjectRepository implements ProjectRepositoryInterface
{
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null,  ?string $status = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_project.projectid',
                'vtiger_project.projectname',
                'vtiger_project.project_no',
                'vtiger_project.startdate',
                'vtiger_project.targetenddate',
                'vtiger_project.actualenddate',
                'vtiger_project.targetbudget',
                'vtiger_project.projecturl',
                'vtiger_project.projectstatus',
                'vtiger_project.projectpriority',
                'vtiger_project.projecttype',
                'vtiger_project.progress',
                'vtiger_project.linktoaccountscontacts',
                'vtiger_account.accountname as account_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_crmentity.description', // 
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.deleted'
            )
            ->where('vtiger_crmentity.deleted', 0);

        // filter by search term
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_project.projectname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_project.project_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.accountname', 'LIKE', "%{$search}%");
            });
        }

        // filter by status (active, completed, cancelled)
        if ($status === 'active') {
            $query->whereNotIn('vtiger_project.projectstatus', [
                'Completado',
                'Completed',
                'Cancelado',
                'Cancelled'
            ]);
        } elseif ($status) {
            // filter by status (completed, cancelled)
            $query->where('vtiger_project.projectstatus', $status);
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        // calculate tasks for all projects
        $projectIds = $items->pluck('projectid')->toArray();
        $allTasks = DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->whereIn('projectid', $projectIds)
            ->get()
            ->groupBy('projectid');

        $projects = $items->map(function ($row) use ($allTasks) {
            $projectId = $row->projectid;
            $tasks = $allTasks->get($projectId, collect());
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('projecttaskstatus', 'Completed')->count();

            return new Project(
                projectid: $row->projectid,
                projectname: $row->projectname,
                project_no: $row->project_no,
                startdate: $row->startdate,
                targetenddate: $row->targetenddate,
                actualenddate: $row->actualenddate,
                targetbudget: $row->targetbudget ? (float)$row->targetbudget : null,
                projecturl: $row->projecturl,
                projectstatus: $row->projectstatus,
                projectpriority: $row->projectpriority,
                projecttype: $row->projecttype,
                progress: $row->progress ? (int)$row->progress : 0,
                linktoaccountscontacts: $row->linktoaccountscontacts ? (int)$row->linktoaccountscontacts : null,
                account_name: $row->account_name ?? null,
                assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
                assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' '
                    ? $row->assigned_user_name
                    : null,
                potential_name: null,
                totalTasks: $totalTasks,
                completedTasks: $completedTasks,
                hits: 0,
                description: $row->description,
                createdtime: $row->createdtime,
                modifiedtime: $row->modifiedtime
            );
        });

        return new LengthAwarePaginator(
            $projects instanceof Collection ? $projects : collect($projects),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function findById(int $projectId): ?Project
    {
        $project = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_project.projectid',
                'vtiger_project.projectname',
                'vtiger_project.project_no',
                'vtiger_project.startdate',
                'vtiger_project.targetenddate',
                'vtiger_project.actualenddate',
                'vtiger_project.targetbudget',
                'vtiger_project.projecturl',
                'vtiger_project.projectstatus',
                'vtiger_project.projectpriority',
                'vtiger_project.projecttype',
                'vtiger_project.progress',
                'vtiger_project.linktoaccountscontacts',
                'vtiger_account.accountname as account_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_crmentity.description',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime'
            )
            ->where('vtiger_project.projectid', $projectId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (!$project) {
            return null;
        }

        // calculate tasks
        $tasks = DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->where('projectid', $projectId)
            ->get();

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('projecttaskstatus', 'Completed')->count();

        return new Project(
            projectid: $project->projectid,
            projectname: $project->projectname,
            project_no: $project->project_no,
            startdate: $project->startdate,
            targetenddate: $project->targetenddate,
            actualenddate: $project->actualenddate,
            targetbudget: $project->targetbudget ? (float)$project->targetbudget : null,
            projecturl: $project->projecturl,
            projectstatus: $project->projectstatus,
            projectpriority: $project->projectpriority,
            projecttype: $project->projecttype,
            progress: $project->progress ? (int)$project->progress : 0,
            linktoaccountscontacts: $project->linktoaccountscontacts ? (int)$project->linktoaccountscontacts : null,
            account_name: $project->account_name ?? null,
            assigned_user_id: $project->assigned_user_id ? (int)$project->assigned_user_id : null,
            assigned_user_name: $project->assigned_user_name && trim($project->assigned_user_name) !== ' '
                ? $project->assigned_user_name
                : null,
            potential_name: null,
            totalTasks: $totalTasks,
            completedTasks: $completedTasks,
            hits: 0,
            description: $project->description,
            createdtime: $project->createdtime,
            modifiedtime: $project->modifiedtime
        );
    }

    public function create(array $data): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // generate project number
            $maxProjectNo = DB::connection('vtiger')
                ->table('vtiger_project')
                ->max('project_no');

            $projectNo = $this->generateProjectNumber($maxProjectNo);

            // Insert into vtiger_crmentity
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    'smownerid' => $data['assigned_user_id'],
                    'smcreatorid' => $data['created_by_user_id'],
                    'setype' => 'Project',
                    'description' => $data['description'] ?? null,
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'deleted' => 0,
                ]);

            // Insert into vtiger_project
            DB::connection('vtiger')
                ->table('vtiger_project')
                ->insert([
                    'projectid' => $crmid,
                    'projectname' => $data['projectname'],
                    'project_no' => $projectNo,
                    'startdate' => $data['startdate'] ?? null,
                    'targetenddate' => $data['targetenddate'] ?? null,
                    'actualenddate' => null,
                    'targetbudget' => $data['targetbudget'] ?? null,
                    'projecturl' => $data['projecturl'] ?? null,
                    'projectstatus' => $data['projectstatus'] ?? 'Draft',
                    'projectpriority' => $data['projectpriority'] ?? 'Medium',
                    'projecttype' => $data['projecttype'] ?? null,
                    'progress' => '0',
                    'linktoaccountscontacts' => $data['accountid'] ?? null,
                    'tags' => null,
                    'isconvertedfrompotential' => 0,
                    'potentialid' => $data['potentialid'] ?? null,
                    'cf_922' => null,
                ]);

            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function update(int $projectId, array $data): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // Actualizar vtiger_project
            DB::connection('vtiger')
                ->table('vtiger_project')
                ->where('projectid', $projectId)
                ->update([
                    'projectname' => $data['projectname'] ?? DB::raw('projectname'),
                    'startdate' => $data['startdate'] ?? DB::raw('startdate'),
                    'targetenddate' => $data['targetenddate'] ?? DB::raw('targetenddate'),
                    'actualenddate' => $data['actualenddate'] ?? DB::raw('actualenddate'),
                    'targetbudget' => $data['targetbudget'] ?? DB::raw('targetbudget'),
                    'projecturl' => $data['projecturl'] ?? DB::raw('projecturl'),
                    'projectstatus' => $data['projectstatus'] ?? DB::raw('projectstatus'),
                    'projectpriority' => $data['projectpriority'] ?? DB::raw('projectpriority'),
                    'projecttype' => $data['projecttype'] ?? DB::raw('projecttype'),
                    'progress' => $data['progress'] ?? DB::raw('progress'),
                    'linktoaccountscontacts' => $data['accountid'] ?? DB::raw('linktoaccountscontacts'),
                    'potentialid' => $data['potentialid'] ?? DB::raw('potentialid'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            // Actualizar vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->update([
                    'smownerid' => $data['assigned_user_id'] ?? DB::raw('smownerid'),
                    'description' => $data['description'] ?? DB::raw('description'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function delete(int $projectId): bool
    {
        try {
            // mark as deleted in vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getTasksByProject(int $projectId): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->where('projectid', $projectId)
            ->get()
            ->toArray();
    }

    private function generateProjectNumber(?string $maxProjectNo): string
    {
        if (!$maxProjectNo) {
            return 'PROJ-' . date('Y') . '-0001';
        }

        // extract the number from the format PROJ-YYYY-XXXX
        if (preg_match('/PROJ-(\d{4})-(\d+)/', $maxProjectNo, $matches)) {
            $year = $matches[1];
            $number = intval($matches[2]);

            // if it's the same year, increment
            if ($year == date('Y')) {
                $number++;
            } else {
                // new year, reset counter
                $number = 1;
            }

            return sprintf('PROJ-%s-%04d', date('Y'), $number);
        }

        return 'PROJ-' . date('Y') . '-0001';
    }
}
