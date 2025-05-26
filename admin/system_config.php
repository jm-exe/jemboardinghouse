<?php
session_start();
require_once '../connection/db.php';

class SystemConfig {
    private $conn;
    public $user_role;
    private $user_id;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function isFirstLogin() {
        if (!$this->user_id) {
            return false;
        }
        $query = "SELECT COUNT(*) as count FROM settings WHERE setting_name = 'initial_setup_completed'";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['count'] == 0 && $this->user_role === 'Admin';
    }

    public function configureSystem($post_data) {
        if ($this->user_role !== 'Admin') {
            return ['status' => 'error', 'message' => 'Unauthorized access. Admins only.'];
        }

        $num_floors = isset($post_data['num_floors']) ? (int)$post_data['num_floors'] : 0;
        $rooms_per_floor = isset($post_data['rooms_per_floor']) ? (int)$post_data['rooms_per_floor'] : 0;
        $beds_per_room = isset($post_data['beds_per_room']) ? (int)$post_data['beds_per_room'] : 0;
        $academic_year_start = isset($post_data['start_year']) ? (int)$post_data['start_year'] : 0;
        $academic_year_end = isset($post_data['end_year']) ? (int)$post_data['end_year'] : 0;
        $semester = isset($post_data['semester']) ? $post_data['semester'] : '';
        $courses = isset($post_data['courses']) ? $post_data['courses'] : [];
        $business_name = isset($post_data['business_name']) ? $post_data['business_name'] : '';
        $owner_name = isset($post_data['owner_name']) ? $post_data['owner_name'] : '';
        $business_address = isset($post_data['business_address']) ? $post_data['business_address'] : '';
        $contact_no = isset($post_data['contact_no']) ? $post_data['contact_no'] : '';
        $new_password = isset($post_data['new_password']) ? $post_data['new_password'] : '';

        if ($num_floors <= 0 || $rooms_per_floor <= 0 || $beds_per_room <= 0 || 
            $academic_year_start <= 0 || $academic_year_end <= 0 || empty($semester) || 
            empty($courses) || empty($business_name) || empty($owner_name) || 
            empty($business_address) || empty($contact_no) || empty($new_password)) {
            return ['status' => 'error', 'message' => 'Invalid input data. Please fill all required fields.'];
        }

        // Validate password strength
        if (strlen($new_password) < 8 || !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $new_password)) {
            return ['status' => 'error', 'message' => 'Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character.'];
        }

        $this->conn->begin_transaction();

        try {
            // Insert business information
            $stmt = $this->conn->prepare("INSERT INTO business_owner (owner_name, address, contact_no, email) VALUES (?, ?, ?, ?)");
            $email = null; // Optional email field
            $stmt->bind_param("ssss", $owner_name, $business_address, $contact_no, $email);
            $stmt->execute();

            // Insert floors, rooms, and beds
            for ($i = 1; $i <= $num_floors; $i++) {
                $floor_no = "FLR-$i";
                $stmt = $this->conn->prepare("INSERT INTO floors (floor_no) VALUES (?)");
                $stmt->bind_param("s", $floor_no);
                $stmt->execute();
                $floor_id = $this->conn->insert_id;

                for ($j = 1; $j <= $rooms_per_floor; $j++) {
                    $room_no = "RM-$j";
                    $stmt = $this->conn->prepare("INSERT INTO rooms (room_no, capacity, floor_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("sii", $room_no, $beds_per_room, $floor_id);
                    $stmt->execute();
                    $room_id = $this->conn->insert_id;

                    for ($k = 1; $k <= $beds_per_room; $k++) {
                        $deck = ($k % 2 == 0) ? 'Upper' : 'Lower';
                        $monthly_rent = 1100.00;
                        $bed_type = 'Double';
                        $stmt = $this->conn->prepare("INSERT INTO beds (bed_no, status, deck, monthly_rent, room_id, bed_type) VALUES (?, 'Vacant', ?, ?, ?, ?)");
                        $stmt->bind_param("isdsi", $k, $deck, $monthly_rent, $room_id, $bed_type);
                        $stmt->execute();
                    }
                }
            }

            // Insert academic year
            $is_current = 1;
            $stmt = $this->conn->prepare("INSERT INTO academic_years (start_year, end_year, semester, is_current) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $academic_year_start, $academic_year_end, $semester, $is_current);
            $stmt->execute();

            // Insert courses
            foreach ($courses as $course) {
                if (!isset($course['code']) || !isset($course['description']) || empty($course['code']) || empty($course['description'])) {
                    continue;
                }
                $course_code = $course['code'];
                $course_description = $course['description'];
                $major = isset($course['major']) ? $course['major'] : null;
                $stmt = $this->conn->prepare("INSERT INTO course (course_code, course_description, major) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $course_code, $course_description, $major);
                $stmt->execute();
            }

            // Insert appliances
            $appliances = [
                ['name' => 'Rice Cooker', 'rate' => 100.00],
                ['name' => 'Electric Fan', 'rate' => 100.00],
                ['name' => 'Laptop', 'rate' => 100.00],
                ['name' => 'Water Heater', 'rate' => 100.00],
                ['name' => 'Flat Iron', 'rate' => 100.00]
            ];
            $stmt = $this->conn->prepare("INSERT INTO appliances (appliance_name, rate) VALUES (?, ?)");
            foreach ($appliances as $appliance) {
                $stmt->bind_param("sd", $appliance['name'], $appliance['rate']);
                $stmt->execute();
            }

            // Insert settings
            $settings = [
                ['name' => 'business_name', 'value' => $business_name],
                ['name' => 'late_payment_rate', 'value' => '2000.00'],
                ['name' => 'overnight_guest_rate', 'value' => '150.00'],
                ['name' => 'overuse_water_fee', 'value' => '250.00'],
                ['name' => 'overuse_electric_fee', 'value' => '250.00'],
                ['name' => 'monthly_rental_rate', 'value' => '1100.00'],
                ['name' => 'late_payment_penalty_per_week', 'value' => '50.00'],
                ['name' => 'minimum_stay_regular', 'value' => '5'],
                ['name' => 'minimum_stay_summer', 'value' => '2'],
                ['name' => 'early_termination_charge', 'value' => '3000.00'],
                ['name' => 'utility_excess_surcharge', 'value' => '100.00'],
                ['name' => 'appliance_additional_charge', 'value' => '100.00'],
                ['name' => 'initial_setup_completed', 'value' => '1.00']
            ];
            $stmt = $this->conn->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");
            foreach ($settings as $setting) {
                $stmt->bind_param("ss", $setting['name'], $setting['value']);
                $stmt->execute();
            }

            // Update admin password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE users SET password = ?, is_first_login = 0 WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $this->user_id);
            $stmt->execute();

            $this->conn->commit();
            return ['status' => 'success', 'message' => 'System configuration completed successfully. Redirecting to dashboard...'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => 'error', 'message' => 'Configuration failed: ' . $e->getMessage()];
        }
    }

    public function displayConfigForm() {
        if ($this->user_role !== 'Admin') {
            return '<div class="alert alert-danger text-center" role="alert">Access denied. Only admins can configure the system.</div>';
        }

        // Initialize session data
        if (!isset($_SESSION['config_data'])) {
            $_SESSION['config_data'] = [
                'num_floors' => '',
                'rooms_per_floor' => '',
                'beds_per_room' => '',
                'start_year' => '',
                'end_year' => '',
                'semester' => '',
                'courses' => [],
                'business_name' => '',
                'owner_name' => '',
                'business_address' => '',
                'contact_no' => '',
                'new_password' => ''
            ];
        }

        // Prepare escaped values
        $num_floors = htmlspecialchars($_SESSION['config_data']['num_floors'] ?? '');
        $rooms_per_floor = htmlspecialchars($_SESSION['config_data']['rooms_per_floor'] ?? '');
        $beds_per_room = htmlspecialchars($_SESSION['config_data']['beds_per_room'] ?? '');
        $start_year = htmlspecialchars($_SESSION['config_data']['start_year'] ?? '');
        $end_year = htmlspecialchars($_SESSION['config_data']['end_year'] ?? '');
        $semester = $_SESSION['config_data']['semester'] ?? '';
        $business_name = htmlspecialchars($_SESSION['config_data']['business_name'] ?? '');
        $owner_name = htmlspecialchars($_SESSION['config_data']['owner_name'] ?? '');
        $business_address = htmlspecialchars($_SESSION['config_data']['business_address'] ?? '');
        $contact_no = htmlspecialchars($_SESSION['config_data']['contact_no'] ?? '');
        $new_password = htmlspecialchars($_SESSION['config_data']['new_password'] ?? '');

        $select_default = $semester === '' ? 'selected="selected"' : '';
        $select_first = $semester === 'First' ? 'selected="selected"' : '';
        $select_second = $semester === 'Second' ? 'selected="selected"' : '';
        $select_summer = $semester === 'Summer' ? 'selected="selected"' : '';

        $courseIndex = count($_SESSION['config_data']['courses']);
        if ($courseIndex === 0) {
            $_SESSION['config_data']['courses'][] = ['code' => '', 'description' => '', 'major' => ''];
            $courseIndex = 1;
        }

        $coursesHtml = '';
        foreach ($_SESSION['config_data']['courses'] as $index => $course) {
            $code = isset($course['code']) ? htmlspecialchars($course['code']) : '';
            $description = isset($course['description']) ? htmlspecialchars($course['description']) : '';
            $major = isset($course['major']) ? htmlspecialchars($course['major']) : '';
            $coursesHtml .= <<<HTML
                <div class="course-entry card p-3 mb-2" data-index="$index">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="course_code_$index" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code_$index" name="courses[$index][code]" value="$code" required>
                            <div class="invalid-feedback">Please enter a course code.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="course_description_$index" class="form-label">Course Description</label>
                            <input type="text" class="form-control" id="course_description_$index" name="courses[$index][description]" value="$description" required>
                            <div class="invalid-feedback">Please enter a course description.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="course_major_$index" class="form-label">Major (Optional)</label>
                            <input type="text" class="form-control" id="course_major_$index" name="courses[$index][major]" value="$major">
                        </div>
                    </div>
                </div>
HTML;
        }

        return <<<HTML
        <div class="full-center-container">
            <div class="config-card">
                <div class="col-md-15">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white text-center">
                            <h4 class="mb-0">Initial System Configuration</h4>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-4">
                                <div class="progress-bar" role="progressbar" style="width: 20%;" id="progressBar">Step 1 of 5</div>
                            </div>
                            <form id="configForm">
                                <!-- Step 1: Business Information -->
                                <div class="step" id="step1">
                                    <h4>Step 1: Business Information</h4>
                                    <div class="mb-3">
                                        <label for="business_name" class="form-label">Business Name</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" value="$business_name" required>
                                        <div class="invalid-feedback">Please enter the business name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="owner_name" class="form-label">Owner Name</label>
                                        <input type="text" class="form-control" id="owner_name" name="owner_name" value="$owner_name" required>
                                        <div class="invalid-feedback">Please enter the owner's name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="business_address" class="form-label">Business Address</label>
                                        <input type="text" class="form-control" id="business_address" name="business_address" value="$business_address" required>
                                        <div class="invalid-feedback">Please enter the business address.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="contact_no" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_no" name="contact_no" value="$contact_no" required>
                                        <div class="invalid-feedback">Please enter a valid contact number.</div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary next-step">Next</button>
                                    </div>
                                </div>

                                <!-- Step 2: Building Setup -->
                                <div class="step d-none" id="step2">
                                    <h4>Step 2: Building Setup</h4>
                                    <div class="mb-3">
                                        <label for="num_floors" class="form-label">Number of Floors</label>
                                        <input type="number" class="form-control" id="num_floors" name="num_floors" min="1" value="$num_floors" required>
                                        <div class="invalid-feedback">Please enter a valid number.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="rooms_per_floor" class="form-label">Rooms per Floor</label>
                                        <input type="number" class="form-control" id="rooms_per_floor" name="rooms_per_floor" min="1" value="$rooms_per_floor" required>
                                        <div class="invalid-feedback">Please enter a valid number.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="beds_per_room" class="form-label">Beds per Room</label>
                                        <input type="number" class="form-control" id="beds_per_room" name="beds_per_room" min="1" value="$beds_per_room" required>
                                        <div class="invalid-feedback">Please enter a valid number.</div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary prev-step">Previous</button>
                                        <button type="button" class="btn btn-primary next-step">Next</button>
                                    </div>
                                </div>

                                <!-- Step 3: Academic Year -->
                                <div class="step d-none" id="step3">
                                    <h4>Step 3: Academic Year</h4>
                                    <div class="mb-3">
                                        <label for="start_year" class="form-label">Academic Year Start</label>
                                        <input type="number" class="form-control" id="start_year" name="start_year" min="2000" max="2100" value="$start_year" required>
                                        <div class="invalid-feedback">Please enter a valid year.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_year" class="form-label">Academic Year End</label>
                                        <input type="number" class="form-control" id="end_year" name="end_year" min="2000" max="2100" value="$end_year" required>
                                        <div class="invalid-feedback">Please enter a valid year.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" name="semester" required>
                                            <option value="" disabled $select_default>Select Semester</option>
                                            <option value="First" $select_first>First</option>
                                            <option value="Second" $select_second>Second</option>
                                            <option value="Summer" $select_summer>Summer</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a semester.</div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary prev-step">Previous</button>
                                        <button type="button" class="btn btn-primary next-step">Next</button>
                                    </div>
                                </div>

                                <!-- Step 4: Courses -->
                                <div class="step d-none" id="step4">
                                    <h4>Step 4: Courses</h4>
                                    <div id="courses" class="mb-3">
                                        $coursesHtml
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary mb-3" onclick="addCourseField()">Add Another Course</button>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary prev-step">Previous</button>
                                        <button type="button" class="btn btn-primary next-step">Next</button>
                                    </div>
                                </div>

                                <!-- Step 5: Password and Review -->
                                <div class="step d-none" id="step5">
                                    <h4>Step 5: Set Password and Review</h4>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" value="$new_password" required>
                                        <div class="invalid-feedback">Password must be at least 8 characters with uppercase, lowercase, number, and special character.</div>
                                    </div>
                                    <div class="card p-3 mb-3">
                                        <h5>Business Information</h5>
                                        <p><strong>Business Name:</strong> <span id="review_business_name"></span></p>
                                        <p><strong>Owner Name:</strong> <span id="review_owner_name"></span></p>
                                        <p><strong>Address:</strong> <span id="review_business_address"></span></p>
                                        <p><strong>Contact Number:</strong> <span id="review_contact_no"></span></p>
                                    </div>
                                    <div class="card p-3 mb-3">
                                        <h5>Building Setup</h5>
                                        <p><strong>Floors:</strong> <span id="review_num_floors"></span></p>
                                        <p><strong>Rooms per Floor:</strong> <span id="review_rooms_per_floor"></span></p>
                                        <p><strong>Beds per Room:</strong> <span id="review_beds_per_room"></span></p>
                                    </div>
                                    <div class="card p-3 mb-3">
                                        <h5>Academic Year</h5>
                                        <p><strong>Start Year:</strong> <span id="review_start_year"></span></p>
                                        <p><strong>End Year:</strong> <span id="review_end_year"></span></p>
                                        <p><strong>Semester:</strong> <span id="review_semester"></span></p>
                                    </div>
                                    <div class="card p-3 mb-3">
                                        <h5>Courses</h5>
                                        <ul id="review_courses"></ul>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary prev-step">Previous</button>
                                        <button type="button" class="btn btn-success" id="submitConfig">Submit Configuration</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
        <script>
            let courseIndex = $courseIndex;
            const steps = document.querySelectorAll('.step');
            const progressBar = document.getElementById('progressBar');
            let currentStep = 0;

            function showStep(stepIndex) {
                steps.forEach((step, index) => {
                    step.classList.toggle('d-none', index !== stepIndex);
                });
                progressBar.style.width = ((stepIndex + 1) * 20) + '%';
                progressBar.textContent = 'Step ' + (stepIndex + 1) + ' of 5';
                currentStep = stepIndex;
            }

            function validateStep(stepIndex) {
                const step = steps[stepIndex];
                const inputs = step.querySelectorAll('input[required], select[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                if (stepIndex === 0) {
                    // Step 1: Business Information
                    const contactNo = document.getElementById('contact_no').value;
                    if (!/^\d{10,11}$/.test(contactNo)) {
                        document.getElementById('contact_no').classList.add('is-invalid');
                        valid = false;
                    } else {
                        document.getElementById('contact_no').classList.remove('is-invalid');
                    }
                }

                if (stepIndex === 2) {
                    // Step 3: Academic Year
                    const startYearInput = document.getElementById('start_year');
                    const endYearInput = document.getElementById('end_year');
                    if (
                        !startYearInput.value ||
                        isNaN(startYearInput.value) ||
                        parseInt(startYearInput.value) < 2000 ||
                        parseInt(startYearInput.value) > 2100
                    ) {
                        startYearInput.classList.add('is-invalid');
                        valid = false;
                    } else {
                        startYearInput.classList.remove('is-invalid');
                    }
                    if (
                        !endYearInput.value ||
                        isNaN(endYearInput.value) ||
                        parseInt(endYearInput.value) < 2000 ||
                        parseInt(endYearInput.value) > 2100 ||
                        parseInt(endYearInput.value) < parseInt(startYearInput.value)
                    ) {
                        endYearInput.classList.add('is-invalid');
                        valid = false;
                    } else {
                        endYearInput.classList.remove('is-invalid');
                    }
                }

                if (stepIndex === 3) {
                    // Step 4: Courses
                    const courses = document.querySelectorAll('.course-entry');
                    if (courses.length === 0) {
                        showAlert('danger', 'At least one course is required.');
                        return false;
                    }
                    let hasValidCourse = false;
                    courses.forEach(entry => {
                        const code = entry.querySelector('[name*="[code]"]').value.trim();
                        const description = entry.querySelector('[name*="[description]"]').value.trim();
                        if (code && description) {
                            hasValidCourse = true;
                        }
                    });
                    if (!hasValidCourse) {
                        showAlert('danger', 'At least one course must have a code and description.');
                        return false;
                    }
                }

                if (stepIndex === 4) {
                    // Step 5: Password
                    const password = document.getElementById('new_password').value;
                    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
                    if (!passwordRegex.test(password)) {
                        document.getElementById('new_password').classList.add('is-invalid');
                        valid = false;
                    } else {
                        document.getElementById('new_password').classList.remove('is-invalid');
                    }
                }

                return valid;
            }

            function addCourseField() {
                const container = document.getElementById('courses');
                const newCourse = document.createElement('div');
                newCourse.className = 'course-entry card p-3 mb-2';
                newCourse.dataset.index = courseIndex;
                newCourse.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <label for="course_code_${courseIndex}" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code_${courseIndex}" name="courses[${courseIndex}][code]" required>
                            <div class="invalid-feedback">Please enter a course code.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="course_description_${courseIndex}" class="form-label">Course Description</label>
                            <input type="text" class="form-control" id="course_description_${courseIndex}" name="courses[${courseIndex}][description]" required>
                            <div class="invalid-feedback">Please enter a course description.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="course_major_${courseIndex}" class="form-label">Major (Optional)</label>
                            <input type="text" class="form-control" id="course_major_${courseIndex}" name="courses[${courseIndex}][major]">
                        </div>
                    </div>
                `;
                container.appendChild(newCourse);
                courseIndex++;
            }

            function updateReview() {
                document.getElementById('review_business_name').textContent = document.getElementById('business_name').value || 'N/A';
                document.getElementById('review_owner_name').textContent = document.getElementById('owner_name').value || 'N/A';
                document.getElementById('review_business_address').textContent = document.getElementById('business_address').value || 'N/A';
                document.getElementById('review_contact_no').textContent = document.getElementById('contact_no').value || 'N/A';
                document.getElementById('review_num_floors').textContent = document.getElementById('num_floors').value || 'N/A';
                document.getElementById('review_rooms_per_floor').textContent = document.getElementById('rooms_per_floor').value || 'N/A';
                document.getElementById('review_beds_per_room').textContent = document.getElementById('beds_per_room').value || 'N/A';
                document.getElementById('review_start_year').textContent = document.getElementById('start_year').value || 'N/A';
                document.getElementById('review_end_year').textContent = document.getElementById('end_year').value || 'N/A';
                document.getElementById('review_semester').textContent = document.getElementById('semester').value || 'N/A';
                const coursesList = document.getElementById('review_courses');
                coursesList.innerHTML = '';
                document.querySelectorAll('.course-entry').forEach(entry => {
                    const code = entry.querySelector('[name*="[code]"]').value.trim();
                    const description = entry.querySelector('[name*="[description]"]').value.trim();
                    const major = entry.querySelector('[name*="[major]"]').value.trim() || 'None';
                    if (code && description) {
                        const li = document.createElement('li');
                        li.textContent = code + ' - ' + description + ' (Major: ' + major + ')';
                        coursesList.appendChild(li);
                    }
                });
            }

            document.querySelectorAll('.next-step').forEach(button => {
                button.addEventListener('click', () => {
                    if (validateStep(currentStep)) {
                        const formData = new FormData(document.getElementById('configForm'));
                        fetch('system_config.php?action=save_step', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            if (data.status === 'success') {
                                if (currentStep === 3) updateReview();
                                if (currentStep < steps.length - 1) {
                                    showStep(currentStep + 1);
                                }
                            } else {
                                showAlert('danger', data.message || 'Failed to save step.');
                            }
                        })
                        .catch(error => {
                            showAlert('danger', 'An error occurred: ' + error.message);
                        });
                    } else {
                        showAlert('danger', 'Please fill all required fields correctly.');
                    }
                });
            });

            document.querySelectorAll('.prev-step').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentStep > 0) {
                        showStep(currentStep - 1);
                    }
                });
            });

            document.getElementById('submitConfig').addEventListener('click', () => {
                if (validateStep(currentStep)) {
                    const formData = new FormData(document.getElementById('configForm'));
                    fetch('system_config.php?action=submit', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        showAlert(data.status, data.message);
                        if (data.status === 'success') {
                            setTimeout(() => {
                                window.location.href = 'dashboard.php';
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        showAlert('danger', 'An error occurred: ' + error.message);
                    });
                } else {
                    showAlert('danger', 'Please fill all required fields correctly.');
                }
            });

            function showAlert(type, message) {
                const alertContainer = document.getElementById('alertContainer');
                alertContainer.innerHTML = `
                    <div class="alert alert-\${type} alert-dismissible fade show" role="alert">
                        \${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }

            showStep(0);
        </script>
HTML;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
    $config = new SystemConfig($conn);
    if (isset($_GET['action']) && $_GET['action'] === 'save_step') {
        $courses = isset($_POST['courses']) ? $_POST['courses'] : [];
        $validCourses = [];
        foreach ($courses as $index => $course) {
            if (isset($course['code'], $course['description']) && !empty($course['code']) && !empty($course['description'])) {
                $validCourses[$index] = [
                    'code' => $course['code'],
                    'description' => $course['description'],
                    'major' => isset($course['major']) ? $course['major'] : ''
                ];
            }
        }
        if (empty($validCourses) && !isset($_POST['courses'])) {
            $validCourses[] = ['code' => '', 'description' => '', 'major' => ''];
        }
        $_SESSION['config_data'] = [
            'num_floors' => isset($_POST['num_floors']) ? $_POST['num_floors'] : (isset($_SESSION['config_data']['num_floors']) ? $_SESSION['config_data']['num_floors'] : ''),
            'rooms_per_floor' => isset($_POST['rooms_per_floor']) ? $_POST['rooms_per_floor'] : (isset($_SESSION['config_data']['rooms_per_floor']) ? $_SESSION['config_data']['rooms_per_floor'] : ''),
            'beds_per_room' => isset($_POST['beds_per_room']) ? $_POST['beds_per_room'] : (isset($_SESSION['config_data']['beds_per_room']) ? $_SESSION['config_data']['beds_per_room'] : ''),
            'start_year' => isset($_POST['start_year']) ? $_POST['start_year'] : (isset($_SESSION['config_data']['start_year']) ? $_SESSION['config_data']['start_year'] : ''),
            'end_year' => isset($_POST['end_year']) ? $_POST['end_year'] : (isset($_SESSION['config_data']['end_year']) ? $_SESSION['config_data']['end_year'] : ''),
            'semester' => isset($_POST['semester']) ? $_POST['semester'] : (isset($_SESSION['config_data']['semester']) ? $_SESSION['config_data']['semester'] : ''),
            'business_name' => isset($_POST['business_name']) ? $_POST['business_name'] : (isset($_SESSION['config_data']['business_name']) ? $_SESSION['config_data']['business_name'] : ''),
            'owner_name' => isset($_POST['owner_name']) ? $_POST['owner_name'] : (isset($_SESSION['config_data']['owner_name']) ? $_SESSION['config_data']['owner_name'] : ''),
            'business_address' => isset($_POST['business_address']) ? $_POST['business_address'] : (isset($_SESSION['config_data']['business_address']) ? $_SESSION['config_data']['business_address'] : ''),
            'contact_no' => isset($_POST['contact_no']) ? $_POST['contact_no'] : (isset($_SESSION['config_data']['contact_no']) ? $_SESSION['config_data']['contact_no'] : ''),
            'new_password' => isset($_POST['new_password']) ? $_POST['new_password'] : (isset($_SESSION['config_data']['new_password']) ? $_SESSION['config_data']['new_password'] : ''),
            'courses' => $validCourses
        ];
        echo json_encode(['status' => 'success', 'message' => 'Step saved']);
        exit;
    } elseif (isset($_GET['action']) && $_GET['action'] === 'submit') {
        $result = $config->configureSystem($_POST);
        echo json_encode($result);
        if ($result['status'] === 'success') {
            unset($_SESSION['config_data']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="CSS/system_config.css">
</head>
<body>
    <?php
    $config = new SystemConfig($conn);
    if ($config->isFirstLogin()) {
        echo $config->displayConfigForm();
    } else {
        if ($config->user_role === 'Admin') {
            header("Location: dashboard.php");
            exit;
        } else {
            echo '<div class="container mt-5"><div class="alert alert-danger text-center" role="alert">Access denied. Only admins can access this page.</div></div>';
        }
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>