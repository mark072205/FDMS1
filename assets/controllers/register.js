document.addEventListener('DOMContentLoaded', function () {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const radios = document.querySelectorAll('input[name="role"]');
    const continueBtn = document.getElementById('continueBtn');
    const clientRadio = document.querySelector('input[name="role"][value="client"]');
    const designerRadio = document.querySelector('input[name="role"][value="designer"]');
    const selectedRoleInput = document.getElementById('selectedRole');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    // Check if role is preselected (from URL route)
    let selectedRole = window.preselectedRole || selectedRoleInput?.value || '';

    function updateButtonText() {
        if (clientRadio.checked) {
            continueBtn.textContent = 'Join as a Client';
            selectedRole = 'client';
        } else if (designerRadio.checked) {
            continueBtn.textContent = 'Join as a Designer';
            selectedRole = 'designer';
        } else {
            continueBtn.textContent = 'Create Account';
        }
    }

    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (continueBtn) {
                continueBtn.disabled = false;
                updateButtonText();
            }
        });
    });

    // Handle continue button click to redirect to appropriate route (only on step 1)
    if (continueBtn) {
        continueBtn.addEventListener('click', () => {
            if (selectedRole === 'designer') {
                window.location.href = '/signup/as-designer';
            } else if (selectedRole === 'client') {
                window.location.href = '/signup/as-client';
            }
        });
    }

    // Password toggle functionality
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon between open and closed eye
            if (type === 'text') {
                // Show open eye icon (password is visible)
                togglePassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            } else {
                // Show closed/crossed eye icon (password is hidden)
                togglePassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            }
        });
    }

    // Confirm password toggle functionality
    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            
            // Toggle icon between open and closed eye
            if (type === 'text') {
                // Show open eye icon (password is visible)
                toggleConfirmPassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            } else {
                // Show closed/crossed eye icon (password is hidden)
                toggleConfirmPassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            }
        });
    }

    // Form validation for submit button
    const signupForm = document.getElementById('signupForm');
    const submitBtn = document.querySelector('.submit-btn');
    const firstNameInput = document.getElementById('firstName');
    const lastNameInput = document.getElementById('lastName');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const termsCheckbox = document.getElementById('terms');

    function validateUsername(username) {
        // Check if username is at least 3 characters and only contains letters, numbers, and underscores
        const usernameRegex = /^[a-zA-Z0-9_]{3,}$/;
        return usernameRegex.test(username);
    }

    function validateEmail(email) {
        // Check if email has @ and a valid domain
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return emailRegex.test(email);
    }

    function validatePassword(password) {
        // Check for at least one uppercase, one lowercase, one number, one special character
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecialChar = /[@$!%*?&_]/.test(password);
        const isLengthValid = password.length >= 8;
        
        // Password is valid if it's at least 8 characters AND has at least one of the character types
        const hasAtLeastOneType = hasUpperCase || hasLowerCase || hasNumber || hasSpecialChar;
        
        return {
            isValid: isLengthValid && hasAtLeastOneType,
            hasUpperCase,
            hasLowerCase,
            hasNumber,
            hasSpecialChar,
            isLengthValid
        };
    }

    function validateForm() {
        if (!submitBtn) return;

        const isFirstNameValid = firstNameInput && firstNameInput.value.trim() !== '';
        const isLastNameValid = lastNameInput && lastNameInput.value.trim() !== '';
        const isUsernameValid = usernameInput && usernameInput.value.trim() !== '' && validateUsername(usernameInput.value);
        const isEmailValid = emailInput && emailInput.value.trim() !== '' && validateEmail(emailInput.value);
        const passwordValidation = validatePassword(passwordInput?.value || '');
        const isPasswordValid = passwordValidation.isValid;
        const confirmPasswordValue = confirmPasswordInput?.value || '';
        const passwordValue = passwordInput?.value || '';
        const isPasswordMatch = confirmPasswordValue === '' || confirmPasswordValue === passwordValue;
        const isTermsChecked = termsCheckbox && termsCheckbox.checked;

        const isFormValid = isFirstNameValid && isLastNameValid && isUsernameValid && isEmailValid && isPasswordValid && isPasswordMatch && isTermsChecked;

        submitBtn.disabled = !isFormValid;
        
        // Show username error message if invalid
        const existingUsernameError = usernameInput?.parentElement.querySelector('.error-message');
        if (usernameInput && usernameInput.value.trim() !== '' && !validateUsername(usernameInput.value)) {
            if (!existingUsernameError) {
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Username must be at least 3 characters and contain only letters, numbers, and underscores';
                usernameInput.parentElement.appendChild(errorMsg);
            }
        } else {
            if (existingUsernameError) {
                existingUsernameError.remove();
            }
        }
        
        // Show email error message if invalid
        const existingEmailError = emailInput?.parentElement.querySelector('.error-message');
        if (emailInput && emailInput.value.trim() !== '' && !validateEmail(emailInput.value)) {
            if (!existingEmailError) {
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Please enter a valid email address (e.g., user@example.com)';
                emailInput.parentElement.appendChild(errorMsg);
            }
        } else {
            if (existingEmailError) {
                existingEmailError.remove();
            }
        }

        // Show confirm password error message if passwords don't match
        const confirmPasswordWrapper = confirmPasswordInput?.parentElement;
        const existingConfirmPasswordError = confirmPasswordWrapper?.parentElement.querySelector('.error-message');
        if (confirmPasswordInput && confirmPasswordValue !== '' && !isPasswordMatch) {
            if (!existingConfirmPasswordError) {
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Passwords do not match';
                confirmPasswordWrapper.parentElement.appendChild(errorMsg);
            }
        } else {
            if (existingConfirmPasswordError) {
                existingConfirmPasswordError.remove();
            }
        }

        // Show password requirements if user is typing, hide if empty
        const passwordWrapper = passwordInput?.parentElement;
        const existingPasswordError = passwordWrapper?.parentElement.querySelector('.password-requirements');
        
        if (passwordInput && passwordInput.value.trim() !== '') {
            if (!existingPasswordError) {
                const requirementsDiv = document.createElement('div');
                requirementsDiv.className = 'password-requirements';
                requirementsDiv.innerHTML = `
                    <div class="requirements-header">Password must have:</div>
                    <div class="requirement ${passwordValidation.isLengthValid ? 'valid' : ''}">
                        <span class="icon">${passwordValidation.isLengthValid ? '✓' : '✗'}</span>
                        At least 8 characters (required)
                    </div>
                    <div class="requirements-subheader">And at least one of the following:</div>
                    <div class="requirement ${passwordValidation.hasUpperCase ? 'valid' : ''}">
                        <span class="icon">${passwordValidation.hasUpperCase ? '✓' : '✗'}</span>
                        One uppercase letter
                    </div>
                    <div class="requirement ${passwordValidation.hasLowerCase ? 'valid' : ''}">
                        <span class="icon">${passwordValidation.hasLowerCase ? '✓' : '✗'}</span>
                        One lowercase letter
                    </div>
                    <div class="requirement ${passwordValidation.hasNumber ? 'valid' : ''}">
                        <span class="icon">${passwordValidation.hasNumber ? '✓' : '✗'}</span>
                        One number
                    </div>
                    <div class="requirement ${passwordValidation.hasSpecialChar ? 'valid' : ''}">
                        <span class="icon">${passwordValidation.hasSpecialChar ? '✓' : '✗'}</span>
                        One special character (@$!%*?&_)
                    </div>
                `;
                passwordWrapper.parentElement.appendChild(requirementsDiv);
            } else {
                // Update existing requirements based on current password value
                const requirements = existingPasswordError.querySelectorAll('.requirement');
                requirements[0].className = `requirement ${passwordValidation.isLengthValid ? 'valid' : ''}`;
                requirements[0].querySelector('.icon').textContent = passwordValidation.isLengthValid ? '✓' : '✗';
                
                requirements[1].className = `requirement ${passwordValidation.hasUpperCase ? 'valid' : ''}`;
                requirements[1].querySelector('.icon').textContent = passwordValidation.hasUpperCase ? '✓' : '✗';
                
                requirements[2].className = `requirement ${passwordValidation.hasLowerCase ? 'valid' : ''}`;
                requirements[2].querySelector('.icon').textContent = passwordValidation.hasLowerCase ? '✓' : '✗';
                
                requirements[3].className = `requirement ${passwordValidation.hasNumber ? 'valid' : ''}`;
                requirements[3].querySelector('.icon').textContent = passwordValidation.hasNumber ? '✓' : '✗';
                
                requirements[4].className = `requirement ${passwordValidation.hasSpecialChar ? 'valid' : ''}`;
                requirements[4].querySelector('.icon').textContent = passwordValidation.hasSpecialChar ? '✓' : '✗';
            }
        } else {
            // Remove requirements display when password field is empty
            if (existingPasswordError) {
                existingPasswordError.remove();
            }
        }
    }

    // Add event listeners to all form fields
    if (signupForm) {
        const formInputs = [firstNameInput, lastNameInput, usernameInput, emailInput, passwordInput, confirmPasswordInput, termsCheckbox];
        
        formInputs.forEach(input => {
            if (input) {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm);
            }
        });

        // Initial validation check
        validateForm();

        // Form submission
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Double-check validation before submitting
            if (submitBtn.disabled) {
                return;
            }

            // Disable button during submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';

            // Prepare form data
            const formData = {
                firstName: firstNameInput.value.trim(),
                lastName: lastNameInput.value.trim(),
                username: usernameInput.value.trim(),
                email: emailInput.value.trim(),
                password: passwordInput.value,
                role: selectedRoleInput.value
            };

            try {
                const response = await fetch('/signup/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    // Redirect to appropriate homepage based on role
                    window.location.href = result.redirectUrl || '/login';
                } else {
                    // Show validation errors
                    if (result.errors) {
                        // Display inline errors for specific fields
                        for (const [field, message] of Object.entries(result.errors)) {
                            let inputElement = null;
                            
                            if (field === 'username') {
                                inputElement = usernameInput;
                            } else if (field === 'email') {
                                inputElement = emailInput;
                            }
                            
                            if (inputElement) {
                                // Remove existing error message
                                const existingError = inputElement.parentElement.querySelector('.error-message');
                                if (existingError) {
                                    existingError.remove();
                                }
                                
                                // Add new error message
                                const errorMsg = document.createElement('div');
                                errorMsg.className = 'error-message';
                                errorMsg.textContent = message;
                                inputElement.parentElement.appendChild(errorMsg);
                            }
                        }
                        
                        // Also show alert with all errors
                        let errorMessage = 'Please fix the following errors:\n';
                        for (const [field, message] of Object.entries(result.errors)) {
                            errorMessage += `\n- ${message}`;
                        }
                        alert(errorMessage);
                    } else {
                        alert(result.message || 'An error occurred. Please try again.');
                    }
                    
                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create my account';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while creating your account. Please try again.');
                
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create my account';
            }
        });
    }
});