<?php
/**
 * Minimal permission helpers used by appraisal pages.
 * Kept separate to avoid redeclaring large common functions.
 */

if (!function_exists('hasAppraisalPermission')) {
    function hasAppraisalPermission($user_id, $role, $appraisal, $user_department = null) {
        $permissions = [
            'can_view' => false,
            'can_edit' => false,
            'can_comment' => false,
            'can_save' => false,
            'reason' => 'No permission'
        ];

        if ($role == 'admin') {
            return [
                'can_view' => true,
                'can_edit' => true,
                'can_comment' => true,
                'can_save' => true,
                'reason' => 'Administrator access'
            ];
        }

        if ($role == 'appraiser' && isset($appraisal['appraiser_id']) && $user_id == $appraisal['appraiser_id']) {
            return [
                'can_view' => true,
                'can_edit' => true,
                'can_comment' => true,
                'can_save' => true,
                'reason' => 'Assigned appraiser'
            ];
        }

        if ($role == 'appraisee' && isset($appraisal['appraisee_id']) && $user_id == $appraisal['appraisee_id']) {
            return [
                'can_view' => true,
                'can_edit' => false,
                'can_comment' => true,
                'can_save' => false,
                'reason' => 'Appraisee access'
            ];
        }

        if ($role == 'hod' && $user_department && isset($appraisal['appraisee_dept']) && $user_department == $appraisal['appraisee_dept']) {
            return [
                'can_view' => true,
                'can_edit' => false,
                'can_comment' => true,
                'can_save' => false,
                'reason' => 'Head of Department access'
            ];
        }

        return $permissions;
    }
}

if (!function_exists('getAppraisalStagePermissions')) {
    function getAppraisalStagePermissions($role, $status, $isOwner = false) {
        $permissions = [
            'can_plan' => false,
            'can_review' => false,
            'can_finalize' => false
        ];

        switch ($role) {
            case 'admin':
                $permissions['can_plan'] = true;
                $permissions['can_review'] = true;
                $permissions['can_finalize'] = true;
                break;

            case 'appraiser':
                if ($isOwner) {
                    $permissions['can_plan'] = $status == 'draft' || $status == 'planning';
                    $permissions['can_review'] = in_array($status, ['planning', 'mid_review']);
                    $permissions['can_finalize'] = in_array($status, ['mid_review', 'final_review']);
                }
                break;

            case 'appraisee':
                $permissions['can_plan'] = false;
                $permissions['can_review'] = in_array($status, ['planning', 'mid_review', 'final_review']);
                $permissions['can_finalize'] = false;
                break;

            case 'hod':
                $permissions['can_plan'] = false;
                $permissions['can_review'] = in_array($status, ['mid_review', 'final_review']);
                $permissions['can_finalize'] = false;
                break;
        }

        return $permissions;
    }
}

if (!function_exists('canEditSection')) {
    function canEditSection($section, $role, $appraisal) {
        if ($role == 'admin') {
            return true;
        }

        $permissions = [
            'appraiser' => [
                'performance_planning' => true,
                'targets' => true,
                'core_competencies' => true,
                'non_core_competencies' => true,
                'appraiser_comments' => true,
                'career_development' => true,
                'promotion_assessment' => true
            ],
            'appraisee' => [
                'performance_planning' => false,
                'targets' => false,
                'core_competencies' => false,
                'non_core_competencies' => false,
                'appraiser_comments' => false,
                'career_development' => false,
                'promotion_assessment' => false,
                'appraisee_comments' => true
            ],
            'hod' => [
                'performance_planning' => false,
                'targets' => false,
                'core_competencies' => false,
                'non_core_competencies' => false,
                'appraiser_comments' => false,
                'career_development' => false,
                'promotion_assessment' => false,
                'hod_comments' => true
            ]
        ];

        return isset($permissions[$role][$section]) ? $permissions[$role][$section] : false;
    }
}
