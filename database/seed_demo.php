<?php

declare(strict_types=1);

/**
 * Seeds realistic demo data (Indian names) for manual testing of every
 * module: Managers and Employees under them, Photographers, Projects and Tasks
 * covering every status the app supports (assigned, accepted, in_progress,
 * completed with a salary credit, exited, and a reassigned task), and
 * Payments covering every Payment Management status (Partially Paid, Fully
 * Paid, Advance Received, Payment Due and Overdue).
 *
 * Every Manager and Employee created here uses the password "admin123", the
 * same default as the bootstrap Admin from seed.php - run that first.
 *
 * Safe to run more than once: existing rows (matched by email, or by name for
 * photographers/projects/tasks, or by project + reference number for payments)
 * are left as they are rather than duplicated.
 *
 * Usage:  php database/seed_demo.php
 */

use App\Core\Database;
use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDescription;
use App\Models\TaskSalaryCredit;
use App\Models\User;
use App\Services\PasswordPolicy;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

const DEMO_PASSWORD = 'admin123';

function findOrCreateUser(
    string $name,
    string $email,
    string $phone,
    string $address,
    string $role,
    ?int $createdBy,
): User {
    $existing = User::findByEmail($email);

    if ($existing !== null) {
        echo "  - {$role}: {$name} already exists - skipped." . PHP_EOL;

        return $existing;
    }

    $id = User::create(
        name:         $name,
        email:        $email,
        phone:        $phone,
        passwordHash: PasswordPolicy::hash(DEMO_PASSWORD),
        role:         $role,
        createdBy:    $createdBy,
        status:       User::STATUS_ACTIVE,
        address:      $address,
    );

    echo "  - {$role}: {$name} <{$email}> created." . PHP_EOL;

    return User::findById($id);
}

function findOrCreatePhotographer(
    string $name,
    string $email,
    string $phone,
    string $address,
    ?int $createdBy,
): Photographer {
    if (Photographer::emailExists($email)) {
        $row = Database::selectOne('SELECT id FROM photographers WHERE email = ? LIMIT 1', [mb_strtolower($email)]);
        echo "  - Photographer: {$name} already exists - skipped." . PHP_EOL;

        return Photographer::findById((int) $row['id']);
    }

    $id = Photographer::create($name, $email, $phone, $address, $createdBy);
    echo "  - Photographer: {$name} created." . PHP_EOL;

    return Photographer::findById($id);
}

function findOrCreateProject(
    int $photographerId,
    string $customerName,
    string $description,
    string $folderName,
    string $deadline,
    float $totalPayment,
    string $status,
    ?int $createdBy,
): Project {
    $row = Database::selectOne(
        'SELECT id FROM projects WHERE photographer_id = ? AND customer_name = ? LIMIT 1',
        [$photographerId, $customerName],
    );

    if ($row !== null) {
        echo "  - Project: {$customerName} already exists - skipped." . PHP_EOL;

        return Project::findById((int) $row['id']);
    }

    $id = Project::create($photographerId, $customerName, $description, $folderName, $deadline, $totalPayment, $createdBy, $status);

    if ($status !== Project::STATUS_PENDING) {
        Project::updateStatus($id, $status);
    }

    echo "  - Project: {$customerName} ({$status}) created." . PHP_EOL;

    return Project::findById($id);
}

function findOrCreatePayment(
    int $projectId,
    float $amount,
    string $paymentType,
    string $paymentMethod,
    string $paymentDate,
    string $referenceNo,
    ?int $receivedBy,
): Payment {
    $row = Database::selectOne(
        'SELECT id FROM payments WHERE project_id = ? AND reference_no = ? LIMIT 1',
        [$projectId, $referenceNo],
    );

    if ($row !== null) {
        echo "    - Payment: {$referenceNo} already exists - skipped." . PHP_EOL;

        return Payment::findById((int) $row['id']);
    }

    $id = Payment::create($projectId, $amount, $paymentType, $paymentMethod, $paymentDate, $referenceNo, null, $receivedBy);

    echo "    - Payment: {$referenceNo} ({$paymentType}, " . money($amount) . ') recorded.' . PHP_EOL;

    return Payment::findById($id);
}

/**
 * Creates a task and drives it to $finalStatus / $progress, crediting salary
 * when it lands on 'completed' - exactly what TaskService does, replayed here
 * without needing a signed-in actor.
 */
function seedTask(
    int $projectId,
    int $employeeId,
    string $title,
    string $description,
    string $startDate,
    string $endDate,
    string $priority,
    float $amount,
    ?int $createdBy,
    string $finalStatus,
    int $progress = 0,
): Task {
    $row = Database::selectOne(
        'SELECT id FROM tasks WHERE project_id = ? AND title = ? LIMIT 1',
        [$projectId, $title],
    );

    if ($row !== null) {
        echo "    - Task: {$title} already exists - skipped." . PHP_EOL;

        return Task::findById((int) $row['id']);
    }

    $id = Task::create($projectId, $employeeId, $title, $description, $startDate, $endDate, $priority, $amount, $createdBy);

    // Row one of the task's own description thread, the same as TaskService.
    TaskDescription::create($id, $description, $createdBy);

    if (in_array($finalStatus, [Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS, Task::STATUS_COMPLETED, Task::STATUS_EXITED], true)) {
        Task::accept($id);
    }

    if ($finalStatus === Task::STATUS_IN_PROGRESS) {
        Task::updateProgress($id, $progress, Task::STATUS_IN_PROGRESS);
    }

    if ($finalStatus === Task::STATUS_COMPLETED) {
        Task::updateProgress($id, 100, Task::STATUS_IN_PROGRESS);
        Task::markCompleted($id);
        TaskSalaryCredit::creditForTask($id, $employeeId, $projectId, $amount);
    }

    if ($finalStatus === Task::STATUS_EXITED) {
        Task::markExited($id);
    }

    echo "    - Task: {$title} ({$finalStatus}) created." . PHP_EOL;

    return Task::findById($id);
}

// ---------------------------------------------------------------------------
// Admin (must already exist - created by database/seed.php)
// ---------------------------------------------------------------------------

$admin = User::findByEmail('admin@gmail.com');

if ($admin === null) {
    fwrite(STDERR, 'No Admin account found. Run `php database/seed.php` first.' . PHP_EOL);
    exit(1);
}

// ---------------------------------------------------------------------------
// Managers
// ---------------------------------------------------------------------------

echo 'Managers' . PHP_EOL;

$rajesh = findOrCreateUser('Rajesh Kumar', 'rajesh.kumar@sunrisefilms.in', '+91 98765 43210', 'A-12 Shivaji Nagar, Pune, Maharashtra', User::ROLE_MANAGER, $admin->id);
$priya  = findOrCreateUser('Priya Sharma', 'priya.sharma@sunrisefilms.in', '+91 98765 43211', '204 Lokhandwala Complex, Andheri West, Mumbai, Maharashtra', User::ROLE_MANAGER, $admin->id);
$vikram = findOrCreateUser('Vikram Singh', 'vikram.singh@sunrisefilms.in', '+91 98765 43212', '17 Civil Lines, Nagpur, Maharashtra', User::ROLE_MANAGER, $admin->id);

// ---------------------------------------------------------------------------
// Employees
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Employees' . PHP_EOL;

$amit    = findOrCreateUser('Amit Patel', 'amit.patel@sunrisefilms.in', '+91 91234 56701', 'B-4 Kothrud, Pune, Maharashtra', User::ROLE_EMPLOYEE, $rajesh->id);
$sneha   = findOrCreateUser('Sneha Reddy', 'sneha.reddy@sunrisefilms.in', '+91 91234 56702', '22 Banjara Hills, Hyderabad, Telangana', User::ROLE_EMPLOYEE, $rajesh->id);
$arjun   = findOrCreateUser('Arjun Nair', 'arjun.nair@sunrisefilms.in', '+91 91234 56703', '9 MG Road, Kochi, Kerala', User::ROLE_EMPLOYEE, $rajesh->id);

$kavita  = findOrCreateUser('Kavita Iyer', 'kavita.iyer@sunrisefilms.in', '+91 91234 56704', '5 Malviya Nagar, Jaipur, Rajasthan', User::ROLE_EMPLOYEE, $priya->id);
$rohan   = findOrCreateUser('Rohan Mehta', 'rohan.mehta@sunrisefilms.in', '+91 91234 56705', '310 Vastrapur, Ahmedabad, Gujarat', User::ROLE_EMPLOYEE, $priya->id);
$anjali  = findOrCreateUser('Anjali Gupta', 'anjali.gupta@sunrisefilms.in', '+91 91234 56706', '14 Sector 22, Chandigarh', User::ROLE_EMPLOYEE, $priya->id);

$suresh  = findOrCreateUser('Suresh Yadav', 'suresh.yadav@sunrisefilms.in', '+91 91234 56707', '77 Indira Nagar, Lucknow, Uttar Pradesh', User::ROLE_EMPLOYEE, $vikram->id);
$neha    = findOrCreateUser('Neha Joshi', 'neha.joshi@sunrisefilms.in', '+91 91234 56708', '3 Camp Area, Nagpur, Maharashtra', User::ROLE_EMPLOYEE, $vikram->id);
$karan   = findOrCreateUser('Karan Malhotra', 'karan.malhotra@sunrisefilms.in', '+91 91234 56709', '48 Model Town, Ludhiana, Punjab', User::ROLE_EMPLOYEE, $vikram->id);

// ---------------------------------------------------------------------------
// Photographers
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Photographers' . PHP_EOL;

$photogAperture    = findOrCreatePhotographer('Aperture Studio', 'studio@aperturestudio.in', '+91 90000 11111', '11 Bandra West, Mumbai, Maharashtra', $admin->id);
$photogOmSai       = findOrCreatePhotographer('Om Sai Productions', 'contact@omsaiproductions.in', '+91 90000 22222', '6 FC Road, Pune, Maharashtra', $priya->id);
$photogMalhotra    = findOrCreatePhotographer('Malhotra Photography', 'studio@malhotraphotography.in', '+91 90000 33333', '19 Karol Bagh, New Delhi', $vikram->id);
$photogFrameworks  = findOrCreatePhotographer('Frameworks Media', 'hello@frameworksmedia.in', '+91 90000 44444', '5th Floor, MG Road, Bengaluru, Karnataka', $admin->id);
$photogNashikClick = findOrCreatePhotographer('Nashik Click Studio', 'contact@nashikclick.in', '+91 90000 55555', 'Mandal Chowk, Nashik, Maharashtra', $rajesh->id);
$photogShutterline = findOrCreatePhotographer('Shutterline Studio', 'studio@shutterline.in', '+91 90000 66666', 'Dharampeth, Nagpur, Maharashtra', $priya->id);

// ---------------------------------------------------------------------------
// Projects
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Projects' . PHP_EOL;

$projWedding = findOrCreateProject(
    $photogAperture->id,
    'Rohit & Simran',
    'Full wedding cinematography and same-day edit across three ceremony days.',
    '2026_Rohit_Simran_Wedding',
    '2026-10-05',
    150000.00,
    Project::STATUS_IN_PROGRESS,
    $rajesh->id,
);

$projPreWedding = findOrCreateProject(
    $photogAperture->id,
    'Rohit & Simran - Pre-Wedding',
    'Two-day pre-wedding shoot at Goa beach locations.',
    'Rohit_Simran_PreWedding_Goa',
    '2026-08-30',
    90000.00,
    Project::STATUS_CANCELLED,
    $vikram->id,
);

$projGaneshSong = findOrCreateProject(
    $photogOmSai->id,
    'Ganesh Utsav Mandal',
    'Devotional music video shot at a local temple with a full crew.',
    'OmSai_Ganesh_Song_2026',
    '2026-09-20',
    80000.00,
    Project::STATUS_IN_PROGRESS,
    $priya->id,
);

$projShortFilm = findOrCreateProject(
    $photogOmSai->id,
    'Paus Film Collective',
    'A 20-minute Marathi short film about a monsoon romance.',
    'OmSai_Paus_ShortFilm',
    '2026-09-05',
    250000.00,
    Project::STATUS_PENDING,
    $admin->id,
);

$projAnniversary = findOrCreateProject(
    $photogMalhotra->id,
    'Malhotra Family',
    'Same-day highlight film for a diamond jubilee anniversary celebration.',
    'Malhotra_Anniversary_2026',
    '2026-09-25',
    60000.00,
    Project::STATUS_COMPLETED,
    $vikram->id,
);

$projBrandAd = findOrCreateProject(
    $photogFrameworks->id,
    'Sunrise Realty Group',
    'A 60-second brand film for a real-estate launch campaign.',
    'SunriseRealty_BrandAd_2026',
    '2026-10-10',
    300000.00,
    Project::STATUS_IN_PROGRESS,
    $admin->id,
);

$projGanpatiHighlights = findOrCreateProject(
    $photogNashikClick->id,
    'Ganpati Utsav Mandal',
    'A 5-minute highlights reel of the ten-day Ganeshotsav celebrations.',
    'GanpatiMandal_Highlights_2026',
    '2026-09-18',
    45000.00,
    Project::STATUS_COMPLETED,
    $rajesh->id,
);

$projAnnualDay = findOrCreateProject(
    $photogShutterline->id,
    'Saraswati Vidyalaya',
    'Full-day multi-camera coverage of the school annual day function.',
    'Saraswati_AnnualDay_2026',
    '2026-12-05',
    70000.00,
    Project::STATUS_PENDING,
    $priya->id,
);

// ---------------------------------------------------------------------------
// Payments (covering every Payment Management status: Partially Paid,
// Fully Paid, Advance Received, Payment Due and Overdue). projShortFilm and
// projAnnualDay are left untouched on purpose: the former's deadline has
// already passed with nothing collected (Overdue), the latter's has not
// (Payment Due).
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Payments' . PHP_EOL;

findOrCreatePayment($projWedding->id, 50000.00, Payment::TYPE_ADVANCE, Payment::METHOD_BANK_TRANSFER, '2026-09-01', 'SEED-WED-ADV', $rajesh->id);
findOrCreatePayment($projWedding->id, 40000.00, Payment::TYPE_MILESTONE, Payment::METHOD_UPI, '2026-09-08', 'SEED-WED-MS1', $rajesh->id);

findOrCreatePayment($projGaneshSong->id, 80000.00, Payment::TYPE_FINAL, Payment::METHOD_CASH, '2026-09-10', 'SEED-GANESH-FULL', $priya->id);

findOrCreatePayment($projAnniversary->id, 60000.00, Payment::TYPE_FINAL, Payment::METHOD_BANK_TRANSFER, '2026-09-24', 'SEED-ANNIV-FULL', $vikram->id);

findOrCreatePayment($projBrandAd->id, 100000.00, Payment::TYPE_ADVANCE, Payment::METHOD_BANK_TRANSFER, '2026-09-05', 'SEED-BRANDAD-ADV', $admin->id);

// ---------------------------------------------------------------------------
// Tasks (covering every lifecycle status)
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Tasks' . PHP_EOL;

$taskWeddingShoot = seedTask($projWedding->id, $amit->id, 'Wedding Day Cinematic Shoot', 'Cinematic coverage of the main wedding ceremony.', '2026-09-10', '2026-10-05', Task::PRIORITY_HIGH, 40000.00, $rajesh->id, Task::STATUS_IN_PROGRESS, 60);
seedTask($projWedding->id, $sneha->id, 'Sangeet Ceremony Coverage', 'Multi-camera coverage of the sangeet night.', '2026-09-08', '2026-09-15', Task::PRIORITY_MEDIUM, 25000.00, $rajesh->id, Task::STATUS_COMPLETED);
seedTask($projWedding->id, $arjun->id, 'Wedding Film Editing & Color Grade', 'Edit and color grade the full wedding film.', '2026-09-20', '2026-10-04', Task::PRIORITY_HIGH, 50000.00, $rajesh->id, Task::STATUS_ASSIGNED);

seedTask($projGaneshSong->id, $kavita->id, 'Song Shoot - Temple Location', 'On-location shoot for the devotional song.', '2026-09-14', '2026-09-18', Task::PRIORITY_HIGH, 20000.00, $priya->id, Task::STATUS_ACCEPTED);
seedTask($projGaneshSong->id, $rohan->id, 'Song Video Editing', 'Edit and sync the devotional song video.', '2026-09-16', '2026-09-19', Task::PRIORITY_MEDIUM, 15000.00, $priya->id, Task::STATUS_IN_PROGRESS, 40);

$exitedScript = seedTask($projShortFilm->id, $anjali->id, 'Script Finalization', 'Finalize the shooting script and shot division.', '2026-08-20', '2026-09-01', Task::PRIORITY_MEDIUM, 10000.00, $admin->id, Task::STATUS_EXITED);

// Reassignment: the exited task above is handed to a new employee - the same
// flow Task Management's "Reassign" action performs (task spec s9).
$reassignRow = Database::selectOne(
    'SELECT id FROM tasks WHERE parent_task_id = ? LIMIT 1',
    [$exitedScript->id],
);

if ($reassignRow === null) {
    Task::markReassigned($exitedScript->id);
    $newId = Task::create(
        $exitedScript->projectId,
        $suresh->id,
        $exitedScript->title,
        $exitedScript->description,
        '2026-09-08',
        '2026-09-15',
        $exitedScript->priority,
        $exitedScript->amount,
        $admin->id,
        $exitedScript->id,
    );
    Task::accept($newId);
    TaskDescription::copyThread($exitedScript->id, $newId);
    Task::updateProgress($newId, 30, Task::STATUS_IN_PROGRESS);
    echo '    - Task: Script Finalization reassigned to Suresh Yadav (in_progress) created.' . PHP_EOL;
} else {
    echo '    - Task: Script Finalization reassignment already exists - skipped.' . PHP_EOL;
}

seedTask($projAnniversary->id, $suresh->id, 'Event Videography', 'Multi-camera coverage of the anniversary event.', '2026-09-15', '2026-09-22', Task::PRIORITY_MEDIUM, 30000.00, $vikram->id, Task::STATUS_COMPLETED);
seedTask($projAnniversary->id, $neha->id, 'Final Film Edit', 'Edit the final anniversary celebration film.', '2026-09-22', '2026-09-24', Task::PRIORITY_MEDIUM, 30000.00, $vikram->id, Task::STATUS_COMPLETED);

seedTask($projBrandAd->id, $karan->id, 'Concept & Storyboard', 'Develop the concept and storyboard for the brand film.', '2026-09-01', '2026-09-08', Task::PRIORITY_HIGH, 40000.00, $admin->id, Task::STATUS_COMPLETED);
seedTask($projBrandAd->id, $amit->id, 'Ad Film Shoot - Day 1', 'Location shoot, day one of two.', '2026-09-10', '2026-09-25', Task::PRIORITY_HIGH, 80000.00, $admin->id, Task::STATUS_IN_PROGRESS, 20);
seedTask($projBrandAd->id, $sneha->id, 'Ad Film Shoot - Day 2', 'Location shoot, day two of two.', '2026-09-26', '2026-10-05', Task::PRIORITY_HIGH, 80000.00, $admin->id, Task::STATUS_ASSIGNED);

seedTask($projGanpatiHighlights->id, $arjun->id, 'Highlights Video Edit', 'Edit the ten-day celebration footage into a highlights reel.', '2026-09-10', '2026-09-17', Task::PRIORITY_LOW, 20000.00, $rajesh->id, Task::STATUS_COMPLETED);

seedTask($projAnnualDay->id, $kavita->id, 'Event Coverage Planning', 'Plan camera positions and shot list for the annual day event.', '2026-11-01', '2026-11-20', Task::PRIORITY_LOW, 10000.00, $priya->id, Task::STATUS_ASSIGNED);

seedTask($projPreWedding->id, $neha->id, 'Pre-Wedding Shoot Editing', 'Edit the Goa pre-wedding shoot footage.', '2026-08-05', '2026-08-25', Task::PRIORITY_MEDIUM, 15000.00, $vikram->id, Task::STATUS_EXITED);

// ---------------------------------------------------------------------------
// Description threads - a photographer sending fresh instructions for work
// that is already out with an employee, which is what the thread exists for.
// ---------------------------------------------------------------------------

echo PHP_EOL . 'Description threads' . PHP_EOL;

$followUps = [
    [$taskWeddingShoot->id, 'Client has asked for drone coverage of the baraat as well. Add it on the morning of the 10th.', $rajesh->id],
    [$taskWeddingShoot->id, 'One more from the client: keep the mandap audio clean, they want the vows usable in the final cut.', $rajesh->id],
];

foreach ($followUps as [$taskId, $body, $author]) {
    $existing = Database::selectOne(
        'SELECT id FROM task_descriptions WHERE task_id = ? AND body = ? LIMIT 1',
        [$taskId, $body],
    );

    if ($existing !== null) {
        echo '  - Follow-up description already exists - skipped.' . PHP_EOL;

        continue;
    }

    TaskDescription::create($taskId, $body, $author);
    echo '  - Follow-up description added to task #' . $taskId . '.' . PHP_EOL;
}

// ---------------------------------------------------------------------------

echo PHP_EOL . 'Demo data is ready. Every Manager and Employee above signs in with:' . PHP_EOL;
echo '  Password: ' . DEMO_PASSWORD . PHP_EOL;
