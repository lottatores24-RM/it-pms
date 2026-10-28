<?php

/*
|--------------------------------------------------------------------------
| Permission labels for the role management screen (FR-004)
|--------------------------------------------------------------------------
|
| Permission names stay in module.action format; these lines only provide
| readable labels. Unknown keys fall back to the raw name in the view.
|
*/

return [

    'modules' => [
        'user' => 'User',
        'role' => 'Role & Permission',
        'project' => 'Proyek',
        'member' => 'Anggota Proyek',
        'task' => 'Task',
        'milestone' => 'Milestone',
        'risk' => 'Risiko',
        'issue' => 'Issue',
        'change_request' => 'Change Request',
        'comment' => 'Komentar',
        'attachment' => 'Lampiran',
        'activity_log' => 'Activity Log',
        'notification' => 'Notifikasi',
        'dashboard' => 'Dashboard',
        'kanban' => 'Kanban',
    ],

    'actions' => [
        'viewAny' => 'Lihat daftar',
        'view' => 'Lihat detail',
        'create' => 'Buat',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'deactivate' => 'Nonaktifkan',
        'close' => 'Tutup',
        'reopen' => 'Buka kembali',
        'updateRole' => 'Ubah role',
        'remove' => 'Keluarkan',
        'updateStatus' => 'Ubah status',
        'review' => 'Review / setujui',
        'deleteSelf' => 'Hapus milik sendiri',
        'upload' => 'Unggah',
        'download' => 'Unduh',
        'moveTask' => 'Pindahkan semua task',
        'moveOwnTask' => 'Pindahkan task sendiri',
    ],

];
