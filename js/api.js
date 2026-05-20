const API_BASE_URL = "../backend/api";

const apiState = {
  currentUser: null
};

function getLoginPageUrl() {
  return "login.html";
}

function redirectToLogin() {
  window.location.href = getLoginPageUrl();
}

function normalizeUrl(url) {
  if (url.startsWith("http://") || url.startsWith("https://")) {
    return url;
  }

  if (url.startsWith("/")) {
    return url;
  }

  return `${API_BASE_URL}/${url}`;
}

async function parseResponse(response) {
  const contentType = response.headers.get("Content-Type") || "";

  if (contentType.includes("application/json")) {
    return response.json();
  }

  const text = await response.text();

  return {
    success: response.ok,
    message: text || "Empty response body."
  };
}

function getHttpErrorMessage(status, payload) {
  if (payload && typeof payload.message === "string" && payload.message.trim() !== "") {
    return payload.message;
  }

  switch (status) {
    case 400:
      return "Невалидна заявка.";
    case 401:
      return "Сесията липсва или е изтекла.";
    case 403:
      return "Нямате права за това действие.";
    case 404:
      return "Ресурсът не беше намерен.";
    case 500:
      return "Възникна вътрешна сървърна грешка.";
    default:
      return "Възникна неочаквана грешка.";
  }
}

async function request(method, url, data = null) {
  const options = {
    method,
    credentials: "include",
    headers: {
      Accept: "application/json"
    }
  };

  if (data !== null) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(data);
  }

  let response;

  try {
    response = await fetch(normalizeUrl(url), options);
  } catch (error) {
    const networkError = new Error("Възникна проблем при връзката със сървъра.");
    networkError.status = 0;
    networkError.cause = error;
    throw networkError;
  }

  const payload = await parseResponse(response);

  if (!response.ok) {
    const error = new Error(getHttpErrorMessage(response.status, payload));
    error.status = response.status;
    error.payload = payload;

    if (response.status === 401) {
      apiState.currentUser = null;
      redirectToLogin();
    }

    throw error;
  }

  return payload;
}

function apiGet(url) {
  return request("GET", url);
}

function apiPost(url, data) {
  return request("POST", url, data);
}

function apiPut(url, data) {
  return request("PUT", url, data);
}

function apiDelete(url) {
  return request("DELETE", url);
}

async function loadCurrentUser() {
  try {
    const response = await apiGet("me.php");
    apiState.currentUser = response.user || null;
    return apiState.currentUser;
  } catch (error) {
    if (error.status === 401) {
      apiState.currentUser = null;
      return null;
    }

    throw error;
  }
}

async function requireAuth() {
  const user = await loadCurrentUser();

  if (!user) {
    redirectToLogin();
    return null;
  }

  return user;
}

async function logout() {
  try {
    await apiPost("logout.php", {});
  } catch (error) {
    if (error.status !== 401) {
      console.error("Logout failed:", error);
    }
  } finally {
    apiState.currentUser = null;
    redirectToLogin();
  }
}
