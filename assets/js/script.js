// Toggle password visibility
function togglePassword(inputId) {
  const passwordInput = document.getElementById(inputId);
  const icon = document.querySelector(`#${inputId} + .password-toggle i`);

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    passwordInput.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

// Format phone number input
function formatPhoneNumber(input) {
  // Remove any non-digit characters
  let phoneNumber = input.value.replace(/\D/g, "");

  // If the phone number starts with '0', remove it
  if (phoneNumber.startsWith("0")) {
    phoneNumber = phoneNumber.substring(1);
  }

  // If the phone number starts with '62', remove it
  if (phoneNumber.startsWith("62")) {
    phoneNumber = phoneNumber.substring(2);
  }

  // Update the input field value
  input.value = phoneNumber;
}

// Password strength validation
function validatePassword(password) {
  // At least 8 characters
  const minLength = password.length >= 8;

  // At least one capital letter
  const hasCapital = /[A-Z]/.test(password);

  // At least one symbol
  const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

  return {
    valid: minLength && hasCapital && hasSymbol,
    minLength,
    hasCapital,
    hasSymbol,
  };
}

// Update password strength indicator
function updatePasswordStrength(password) {
  const result = validatePassword(password);
  const strengthFeedback = document.getElementById(
    "password-strength-feedback"
  );

  if (!strengthFeedback) return;

  strengthFeedback.innerHTML = "";

  if (!result.minLength) {
    const item = document.createElement("div");
    item.textContent = "✖ Password harus minimal 8 karakter";
    item.className = "text-danger";
    strengthFeedback.appendChild(item);
  } else {
    const item = document.createElement("div");
    item.textContent = "✓ Password minimal 8 karakter";
    item.className = "text-success";
    strengthFeedback.appendChild(item);
  }

  if (!result.hasCapital) {
    const item = document.createElement("div");
    item.textContent = "✖ Password harus memiliki minimal 1 huruf kapital";
    item.className = "text-danger";
    strengthFeedback.appendChild(item);
  } else {
    const item = document.createElement("div");
    item.textContent = "✓ Password memiliki huruf kapital";
    item.className = "text-success";
    strengthFeedback.appendChild(item);
  }

  if (!result.hasSymbol) {
    const item = document.createElement("div");
    item.textContent =
      "✖ Password harus memiliki minimal 1 simbol (!@#$%^&*()_+-=[]{};':\"\\|,.<>/?)";
    item.className = "text-danger";
    strengthFeedback.appendChild(item);
  } else {
    const item = document.createElement("div");
    item.textContent = "✓ Password memiliki simbol";
    item.className = "text-success";
    strengthFeedback.appendChild(item);
  }
}

// Validate registration form
function validateRegistrationForm() {
  const name = document.getElementById("name").value.trim();
  const phone = document.getElementById("phone").value.trim();
  const password = document.getElementById("password").value;

  if (name === "") {
    alert("Nama tidak boleh kosong");
    return false;
  }

  if (phone === "") {
    alert("Nomor HP tidak boleh kosong");
    return false;
  }

  const passwordCheck = validatePassword(password);
  if (!passwordCheck.valid) {
    alert("Password tidak memenuhi persyaratan");
    return false;
  }

  return true;
}

// Document ready function
document.addEventListener("DOMContentLoaded", function () {
  // Initialize any elements that need it
  const passwordField = document.getElementById("password");
  if (passwordField) {
    passwordField.addEventListener("input", function () {
      updatePasswordStrength(this.value);
    });
  }

  // Initialize Bootstrap tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Initialize Bootstrap toasts
  const toastElList = [].slice.call(document.querySelectorAll(".toast"));
  const toastList = toastElList.map(function (toastEl) {
    return new bootstrap.Toast(toastEl);
  });

  // Show toasts
  toastList.forEach((toast) => toast.show());
});
