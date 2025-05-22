$(document).ready(function() {
    const razorpayKey = 'rzp_test_icdVAXazudEtAP';
    let studentFees = [];
    let studentIdForFees = null;
    let filterData = {};

    function showError(elementId, message) {
        console.log(`Error displayed: ${message}`);
        $(`#${elementId}`).text(message).show();
        setTimeout(() => $(`#${elementId}`).hide(), 5000);
    }

    function formatDate(date) {
        return date ? new Date(date).toLocaleDateString('en-GB') : '-';
    }

    function generateReceipt(fee, paymentId) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(18);
        doc.text('Payment Receipt', 20, 20);
        doc.setFontSize(12);
        doc.text(`Student ID: ${fee.student_id}`, 20, 40);
        doc.text(`Fee Type: ${fee.fee_type}`, 20, 50);
        doc.text(`Amount: ₹${fee.amount}`, 20, 60);
        doc.text(`Payment Date: ${formatDate(fee.paid_date)}`, 20, 70);
        doc.text(`Payment ID: ${paymentId}`, 20, 80);
        doc.save(`receipt_${fee.id}.pdf`);
    }

    function populateFilterValues(selectId, filterType) {
        const values = filterData[filterType + 's'] || [];
        const $select = $(`#${selectId}`);
        $select.empty().append('<option value="">Select value</option>');
        values.forEach(value => {
            $select.append(`<option value="${value}">${value}</option>`);
        });
    }

    
    function checkAuth(callback) {
        $.ajax({
            url: '../backend/check_auth.php',
            method: 'GET',
            credentials: 'include',
            success: function(response) {
                console.log('Auth check response:', response);
                if (response.success) {
                    callback(response.user_type);
                } else {
                    console.log('User not authenticated, redirecting to login');
                    window.location.href = 'student_login.html';
                }
            },
            error: function(xhr) {
                console.error('Auth check error:', xhr);
                window.location.href = 'student_login.html';
            }
        });
    }

    
    $('.tab').click(function() {
        const tab = $(this).data('tab');
        console.log(`Tab clicked: ${tab}`);

        $('.tab').removeClass('active');
        $(this).addClass('active');

        $('.form').removeClass('active');
        if (tab === 'student') {
            $('#student-signup-form').addClass('active');
        } else if (tab === 'admin') {
            $('#admin-signup-form').addClass('active');
        }
    });

    function loadAdminDashboard() {
        checkAuth(function(userType) {
            $.ajax({
                url: '../backend/admin.php',
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    if (response.success) {
                        $('#total-students').text(response.overview.total_students);
                        $('#total-payments').text(`₹${response.overview.total_payments}`);
                        $('#unpaid-fees').text(response.overview.unpaid_fees);

                        filterData = response.filters;

                        $('#students-table').DataTable({
                            data: response.students,
                            columns: [
                                { data: 'unique_id' },
                                { data: 'name' },
                                { data: 'email' },
                                { data: 'semester' },
                                { data: 'branch' },
                                { data: 'course' },
                                { data: 'batch' },
                                {
                                    data: null,
                                    render: function(data) {
                                        return `<button class="btn btn-edit" onclick="showFeeHistory('${data.unique_id}')">Fees</button>`;
                                    }
                                }
                            ],
                            responsive: true,
                            destroy: true
                        });

                        $('#payments-table').DataTable({
                            data: response.payments,
                            columns: [
                                { data: 'name' },
                                { data: 'fee_type' },
                                { data: 'amount', render: data => `₹${data}` },
                                { data: 'due_date', render: formatDate },
                                { data: 'paid_date', render: formatDate },
                                { data: 'status' }
                            ],
                            responsive: true,
                            destroy: true
                        });

                        $('#tokens-table').DataTable({
                            data: response.tokens,
                            columns: [
                                { data: 'name' },
                                { data: 'token' },
                                { data: 'slot_date', render: data => data ? new Date(data).toLocaleDateString('en-GB') : '-' },
                                { data: 'slot_time' },
                                { data: 'created_at', render: data => new Date(data).toLocaleString('en-GB') },
                                { data: 'expiry_date', render: data => new Date(data).toLocaleString('en-GB') },
                                { data: 'status' },
                                {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-approve" onclick="updateToken(${data.id}, 'Approved')">Approve</button>
                                            <button class="btn btn-reject" onclick="updateToken(${data.id}, 'Rejected')">Reject</button>
                                        `;
                                    }
                                }
                            ],
                            responsive: true,
                            destroy: true
                        });
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    showError('error', 'Failed to load dashboard: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });
        });
    }

    function loadStudentDashboard() {
        checkAuth(function(userType) {
            console.log('Loading student dashboard');
            $.ajax({
                url: '../backend/fees.php',
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    console.log('Fees response:', response);
                    if (response.success) {
                        studentFees = response.fees;
                        const container = $('#fees-container');
                        container.empty();
                        if (response.fees.length === 0) {
                            container.append('<p class="no-data">No pending fees</p>');
                        } else {
                            response.fees.forEach(fee => {
                                container.append(`
                                    <div class="card">
                                        <h4>${fee.fee_type}</h4>
                                        <p>Amount: ₹${fee.amount}</p>
                                        <p>Due Date: ${formatDate(fee.due_date)}</p>
                                        <p>Status: ${fee.status}</p>
                                        <button class="btn btn-pay" onclick="payOnline(${fee.id}, ${fee.amount}, '${fee.fee_type}')">Pay Online</button>
                                        <button class="btn btn-token" onclick="generateToken(${fee.id})">Generate Token</button>
                                    </div>
                                `);
                            });
                        }
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Fees fetch error:', xhr);
                    showError('error', 'Failed to load fees: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });

            $.ajax({
                url: '../backend/notifications.php',
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    console.log('Notifications response:', response);
                    if (response.success && response.unread_count > 0) {
                        $('#notif-count').addClass('unread').text(`Notifications (${response.unread_count})`);
                    }
                },
                error: function(xhr) {
                    console.error('Notifications fetch error:', xhr);
                }
            });
        });
    }

    function loadFeesHistory() {
        checkAuth(function(userType) {
            const isAdmin = userType === 'admin' || window.location.search.includes('admin=true');
            const studentId = new URLSearchParams(window.location.search).get('student_id');
            const url = studentId ? `../backend/fees.php?action=history&student_id=${studentId}` : '../backend/fees.php?action=history';
            
            if (isAdmin) {
                $('#admin-nav').removeClass('hidden');
                $('#student-header').removeClass('hidden');
            } else {
                $('#student-nav').removeClass('hidden');
            }

            $.ajax({
                url: url,
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    console.log('Fees history response:', response);
                    if (response.success) {
                        const columns = isAdmin ? [
                            { data: 'student_name' },
                            { data: 'fee_type' },
                            { data: 'amount', render: data => `₹${data}` },
                            { data: 'due_date', render: formatDate },
                            { data: 'paid_date', render: formatDate },
                            { data: 'status' },
                            {
                                data: null,
                                render: function(data) {
                                    let buttons = '';
                                    if (data.status === 'Paid' && data.payment_id) {
                                        buttons += `<button class="btn btn-download" onclick="generateReceipt(${JSON.stringify(data)}, '${data.payment_id}')">Download Receipt</button>`;
                                    }
                                    if (isAdmin) {
                                        buttons += ` <button class="btn btn-edit" onclick="editFee(${data.id}, '${data.student_name}', '${data.fee_type}', ${data.amount}, '${data.due_date}', '${data.status}', '${data.paid_date}')">Edit</button>`;
                                    }
                                    return buttons;
                                }
                            }
                        ] : [
                            { data: 'fee_type' },
                            { data: 'amount', render: data => `₹${data}` },
                            { data: 'due_date', render: formatDate },
                            { data: 'paid_date', render: formatDate },
                            { data: 'status' },
                            {
                                data: null,
                                render: function(data) {
                                    let buttons = '';
                                    if (data.status === 'Paid' && data.payment_id) {
                                        buttons += `<button class="btn btn-download" onclick="generateReceipt(${JSON.stringify(data)}, '${data.payment_id}')">Download Receipt</button>`;
                                    }
                                    return buttons;
                                }
                            }
                        ];

                        $('#fees-table').DataTable({
                            data: response.fees,
                            columns: columns,
                            responsive: true,
                            destroy: true
                        });
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Fees history fetch error:', xhr);
                    showError('error', 'Failed to load fees history: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });
        });
    }

    function loadStudentProfile() {
        checkAuth(function() {
            $.ajax({
                url: '../backend/student.php',
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    if (response.success) {
                        $('#name').val(response.profile.name);
                        $('#father-name').val(response.profile.father_name);
                        $('#dob').val(response.profile.dob);
                        $('#semester').val(response.profile.semester);
                        $('#branch').val(response.profile.branch);
                        $('#course').val(response.profile.course);
                        $('#batch').val(response.profile.batch);
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    showError('error', 'Failed to load profile: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });
        });
    }

    function loadNotifications() {
        checkAuth(function() {
            $.ajax({
                url: '../backend/notifications.php',
                method: 'GET',
                credentials: 'include',
                success: function(response) {
                    console.log('Notifications response:', response);
                    if (response.success) {
                        const container = $('#notifications-container');
                        container.empty();
                        if (response.notifications.length === 0) {
                            container.append('<p class="no-data">No notifications</p>');
                        } else {
                            response.notifications.forEach(notif => {
                                const isUnread = !notif.is_read;
                                container.append(`
                                    <div class="card ${isUnread ? 'unread' : ''}" onclick="markNotificationRead(${notif.id}, this)">
                                        <h4>${notif.type}</h4>
                                        <p>${notif.message}</p>
                                        <p>${new Date(notif.created_at).toLocaleString('en-GB')}</p>
                                    </div>
                                `);
                            });
                        }
                        if (response.unread_count > 0) {
                            $('#notif-count').addClass('unread').text(`Notifications (${response.unread_count})`);
                        } else {
                            $('#notif-count').removeClass('unread').text('Notifications');
                        }
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Notifications fetch error:', xhr);
                    showError('error', 'Failed to load notifications: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });
        });
    }

    window.logout = function() {
        $.ajax({
            url: '../backend/login.php?logout=true',
            method: 'GET',
            credentials: 'include',
            success: function(response) {
                if (response.success) {
                    console.log('Logout successful');
                    window.location.href = 'student_login.html';
                }
            },
            error: function(xhr) {
                console.error('Logout error:', xhr);
            }
        });
    };

    window.showFeeHistory = function(studentId) {
        studentIdForFees = studentId;
        $.ajax({
            url: `../backend/fees.php?action=history&student_id=${studentId}`,
            method: 'GET',
            credentials: 'include',
            success: function(response) {
                if (response.success) {
                    $('#fee-history-table').DataTable({
                        data: response.fees,
                        columns: [
                            { data: 'fee_type' },
                            { data: 'amount', render: data => `₹${data}` },
                            { data: 'due_date', render: formatDate },
                            { data: 'paid_date', render: formatDate },
                            { data: 'status' },
                            {
                                data: null,
                                render: function(data) {
                                    return `<button class="btn btn-edit" onclick="editFee(${data.id}, '${data.student_name}', '${data.fee_type}', ${data.amount}, '${data.due_date}', '${data.status}', '${data.paid_date}')">Edit</button>`;
                                }
                            }
                        ],
                        responsive: true,
                        destroy: true
                    });
                    $('#fee-modal').show();
                } else {
                    showError('fee-error', response.message);
                }
            },
            error: function(xhr) {
                showError('fee-error', 'Failed to load fee history: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    };

    window.editFee = function(feeId, studentName, feeType, amount, dueDate, status, paidDate) {
        $('#edit-fee-id').val(feeId);
        $('#edit-student-id').val(studentIdForFees);
        $('#edit-fee-type').val(feeType);
        $('#edit-amount').val(amount);
        $('#edit-due-date').val(dueDate.split('/').reverse().join('-'));
        $('#edit-status').val(status);
        $('#edit-paid-date').val(paidDate ? paidDate.split('/').reverse().join('-') : '');
        $('#edit-fee-modal').show();
    };

    window.updateToken = function(tokenId, status) {
        $.ajax({
            url: '../backend/tokens.php',
            method: 'PUT',
            
            contentType: 'application/json',
            data: JSON.stringify({ id: tokenId, status: status }),
            success: function(response) {
                if (response.success) {
                    alert('Token status updated successfully');
                    loadAdminDashboard();
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                console.error('Token update error:', xhr);
                showError('error', 'Failed to update token status: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    };

    window.payOnline = function(feeId, amount, feeType) {
        const fee = studentFees.find(f => f.id === feeId);
        const options = {
            key: razorpayKey,
            amount: amount * 100,
            currency: 'INR',
            name: 'CollegeSync Fees',
            description: feeType,
            handler: function(response) {
                $.ajax({
                    url: '../backend/fees.php',
                    method: 'POST',
                    credentials: 'include',
                    contentType: 'application/json',
                    data: JSON.stringify({ fee_id: feeId, payment_id: response.razorpay_payment_id }),
                    success: function(res) {
                        if (res.success) {
                            alert('Payment successful');
                            fee.status = 'Paid';
                            fee.paid_date = new Date().toISOString();
                            generateReceipt(fee, response.razorpay_payment_id);
                            loadStudentDashboard();
                        } else {
                            showError('error', res.message);
                        }
                    },
                    error: function(xhr) {
                        showError('error', 'Payment verification failed: ' + (xhr.responseJSON?.message || 'Server error'));
                    }
                });
            }
        };
        const rzp = new Razorpay(options);
        rzp.open();
    };

    window.generateToken = function(feeId) {
    console.log('Generating token for feeId:', feeId);
    if (!feeId || isNaN(feeId)) {
        showError('error', 'Invalid fee ID');
        return;
    }
    $.ajax({
        url: '../backend/tokens.php',
        method: 'POST',
        credentials: 'include',
        contentType: 'application/json',
        data: JSON.stringify({ fee_id: feeId }),
        dataType: 'json',
        success: function(response) {
            console.log('Token generation response:', response);
            if (response.success) {
                alert(`Token generated: ${response.token}\n` +
                      `Visit accounts section on: ${new Date(response.slot_date).toLocaleDateString('en-GB')} at ${response.slot_time}\n` +
                      `Valid until: ${new Date(response.expiry_date).toLocaleString('en-GB')}`);
            } else {
                showError('error', response.message || 'Token generation failed');
            }
        },
        error: function(xhr, status, error) {
            console.error('Token generation AJAX error:', { status, error, response: xhr.responseText });
            showError('error', `Failed to generate token: ${xhr.responseJSON?.message || xhr.statusText || 'Server error'}`);
        }
    });
};

    window.markNotificationRead = function(notificationId, element) {
        checkAuth(function() {
            $.ajax({
                url: '../backend/notifications.php',
                method: 'PUT',
                credentials: 'include',
                contentType: 'application/json',
                data: JSON.stringify({ notification_id: notificationId }),
                success: function(response) {
                    if (response.success) {
                        $(element).removeClass('unread');
                        loadNotifications();
                    } else {
                        showError('error', response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Mark notification error:', xhr);
                    showError('error', 'Failed to mark notification as read: ' + (xhr.responseJSON?.message || 'Server error'));
                }
            });
        });
    };

    $('#student-login-form').submit(function(e) {
        e.preventDefault();
        const email = $('#email').val();
        const password = $('#password').val();
        console.log('Student login attempt:', { email });
        $.ajax({
            url: '../backend/login.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                email: email,
                password: password,
                type: 'student'
            }),
            success: function(response) {
                console.log('Student login response:', response);
                if (response.success) {
                    console.log('Redirecting to student_dashboard.html');
                    window.location.href = 'student_dashboard.html';
                } else {
                    showError('error', response.message || 'Login failed');
                }
            },
            error: function(xhr) {
                console.error('Student login error:', xhr);
                showError('error', 'Login failed: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#admin-login-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/login.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                email: $('#username').val(),
                password: $('#password').val(),
                type: 'admin'
            }),
            success: function(response) {
                if (response.success) {
                    window.location.href = 'admin_dashboard.html';
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                showError('error', 'Login failed: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#student-signup-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/student.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                name: $('#student-name').val(),
                father_name: $('#father-name').val(),
                dob: $('#dob').val(),
                semester: $('#semester').val(),
                branch: $('#branch').val(),
                course: $('#course').val(),
                batch: $('#batch').val(),
                email: $('#student-email').val(),
                password: $('#student-password').val()
            }),
            success: function(response) {
                if (response.success) {
                    alert('Registration successful! Your ID: ' + response.unique_id);
                    window.location.href = 'student_login.html';
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                showError('error', 'Registration failed: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#admin-signup-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/admin.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'admin_signup',
                username: $('#admin-username').val(),
                password: $('#admin-password').val()
            }),
            success: function(response) {
                if (response.success) {
                    alert('Admin registration successful! Please login.');
                    window.location.href = 'admin_login.html';
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                showError('error', 'Admin registration failed: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#add-student-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/admin.php',
            method: 'POST',
            
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'add_student',
                name: $('#student-name').val(),
                father_name: $('#father-name').val(),
                dob: $('#dob').val(),
                semester: $('#semester').val(),
                branch: $('#branch').val(),
                course: $('#course').val(),
                batch: $('#batch').val(),
                email: $('#student-email').val(),
                password: $('#student-password').val()
            }),
            success: function(response) {
                if (response.success) {
                    alert('Student added successfully! ID: ' + response.unique_id);
                    $('#add-student-form')[0].reset();
                    loadAdminDashboard();
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                showError('error', 'Failed to add student: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#assign-fees-form').submit(function(e) {
        e.preventDefault();
        const filterType = $('#filter-type').val();
        const filterValue = filterType === 'all' ? '' : $('#filter-value').val();
        if (filterType !== 'all' && !filterValue) {
            showError('assign-fees-error', 'Please select a filter value');
            return;
        }
        $.ajax({
            url: '../backend/admin.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'assign_fees',
                filter: filterType,
                value: filterValue,
                fee_type: $('#fee_type').val(),
                amount: $('#amount').val(),
                due_date: $('#due_date').val()
            }),
            success: function(response) {
                if (response.success) {
                    alert(`Fees assigned to ${response.affected} students successfully`);
                    $('#assign-fees-form')[0].reset();
                    $('#filter-value').addClass('hidden');
                    loadAdminDashboard();
                } else {
                    showError('assign-fees-error', response.message);
                }
            },
            error: function(xhr) {
                showError('assign-fees-error', 'Failed to assign fees: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#broadcast-form').submit(function(e) {
        e.preventDefault();
        const filterType = $('#filter-type-notif').val();
        const filterValue = filterType === 'all' ? '' : $('#filter-value-notif').val();
        if (filterType !== 'all' && !filterValue) {
            showError('broadcast-error', 'Please select a filter value');
            return;
        }
        $.ajax({
            url: '../backend/notifications.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                filter: filterType,
                value: filterValue,
                message: $('#broadcast-message').val(),
                type: 'Info'
            }),
            success: function(response) {
                if (response.success) {
                    alert(`Notification sent to ${response.affected} students successfully`);
                    $('#broadcast-form')[0].reset();
                    $('#filter-value-notif').addClass('hidden');
                } else {
                    showError('broadcast-error', response.message);
                }
            },
            error: function(xhr) {
                showError('broadcast-error', 'Failed to send notification: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#profile-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/student.php',
            method: 'PUT',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                name: $('#name').val(),
                father_name: $('#father-name').val(),
                dob: $('#dob').val(),
                semester: $('#semester').val(),
                branch: $('#branch').val(),
                course: $('#course').val(),
                batch: $('#batch').val()
            }),
            success: function(response) {
                if (response.success) {
                    alert('Profile updated successfully');
                } else {
                    showError('error', response.message);
                }
            },
            error: function(xhr) {
                showError('error', 'Failed to update profile: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#edit-fee-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../backend/admin.php',
            method: 'POST',
            credentials: 'include',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'edit_fee',
                fee_id: $('#edit-fee-id').val(),
                student_id: $('#edit-student-id').val(),
                fee_type: $('#edit-fee-type').val(),
                amount: $('#edit-amount').val(),
                due_date: $('#edit-due-date').val(),
                status: $('#edit-status').val(),
                paid_date: $('#edit-paid-date').val() || null
            }),
            success: function(response) {
                if (response.success) {
                    alert('Fee updated successfully');
                    $('#edit-fee-modal').hide();
                    showFeeHistory(studentIdForFees);
                } else {
                    showError('fee-error', response.message);
                }
            },
            error: function(xhr) {
                showError('fee-error', 'Failed to update fee: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    });

    $('#filter-type').change(function() {
        const filterType = $(this).val();
        if (filterType === 'all') {
            $('#filter-value').addClass('hidden');
        } else {
            $('#filter-value').removeClass('hidden');
            populateFilterValues('filter-value', filterType);
        }
    });

    $('#filter-type-notif').change(function() {
        const filterType = $(this).val();
        if (filterType === 'all') {
            $('#filter-value-notif').addClass('hidden');
        } else {
            $('#filter-value-notif').removeClass('hidden');
            populateFilterValues('filter-value-notif', filterType);
        }
    });

    $('.close').click(function() {
        $(this).closest('.modal').hide();
    });

    if (window.location.pathname.includes('admin_dashboard.html')) {
        checkAuth(loadAdminDashboard);
    } else if (window.location.pathname.includes('student_dashboard.html')) {
        loadStudentDashboard();
    } else if (window.location.pathname.includes('fees_history.html')) {
        loadFeesHistory();
    } else if (window.location.pathname.includes('student_profile.html')) {
        loadStudentProfile();
    } else if (window.location.pathname.includes('notifications.html')) {
        loadNotifications();
    }
});