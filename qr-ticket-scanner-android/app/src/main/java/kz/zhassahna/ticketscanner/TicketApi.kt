package kz.zhassahna.ticketscanner

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.FormBody
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject

data class TicketDetails(
    val uid: String = "",
    val eventTitle: String = "",
    val sessionStart: String = "",
    val seat: String = "",
    val customer: String = ""
)

data class ScanResult(
    val accepted: Boolean,
    val duplicate: Boolean = false,
    val message: String,
    val details: TicketDetails = TicketDetails()
)

class TicketApi(private val baseUrl: String) {
    private val cookies = mutableMapOf<String, List<Cookie>>()
    private val cookieJar = object : CookieJar {
        override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
            val stored = this@TicketApi.cookies[url.host]
                .orEmpty()
                .associateBy { it.name }
                .toMutableMap()
            cookies.forEach { stored[it.name] = it }
            this@TicketApi.cookies[url.host] = stored.values.toList()
        }

        override fun loadForRequest(url: HttpUrl): List<Cookie> {
            return cookies[url.host].orEmpty().filter { it.matches(url) }
        }
    }

    private val client = OkHttpClient.Builder()
        .cookieJar(cookieJar)
        .followRedirects(true)
        .build()

    private var csrfToken: String = ""

    suspend fun login(username: String, password: String): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching {
                val loginUrl = "${baseUrl.trimEnd('/')}/login.php"
                val loginPage = Request.Builder().url(loginUrl).get().build()
                val csrfResponse = client.newCall(loginPage).execute()
                val csrfHtml = csrfResponse.use { it.body?.string().orEmpty() }
                csrfToken = Regex(
                    """name=["']csrf_token["'][^>]*value=["']([^"']+)["']""",
                    RegexOption.IGNORE_CASE
                ).find(csrfHtml)?.groupValues?.getOrNull(1).orEmpty()
                check(csrfToken.isNotBlank()) { "Не удалось получить токен входа." }

                val form = FormBody.Builder()
                    .add("username", username)
                    .add("password", password)
                    .add("csrf_token", csrfToken)
                    .build()
                val loginRequest = Request.Builder()
                    .url(loginUrl)
                    .post(form)
                    .build()
                val response = client.newCall(loginRequest).execute()
                val finalPath = response.use { it.request.url.encodedPath }
                check(!finalPath.endsWith("/login.php")) {
                    "Неверное имя пользователя или пароль."
                }
            }
        }

    suspend fun checkIn(code: String, deviceUid: String): Result<ScanResult> =
        withContext(Dispatchers.IO) {
            runCatching {
                val url = "${baseUrl.trimEnd('/')}/ajax/ticket.php?action=checkin"
                val form = FormBody.Builder()
                    .add("code", code)
                    .add("device_uid", deviceUid)
                    .add("csrf_token", csrfToken)
                    .build()
                val request = Request.Builder().url(url).post(form).build()
                val response = client.newCall(request).execute()
                val statusCode = response.code
                val body = response.use { it.body?.string().orEmpty() }
                val json = JSONObject(body)
                val data = json.optJSONObject("data")
                val details = TicketDetails(
                    uid = data?.optString("ticket_uid").orEmpty(),
                    eventTitle = data?.optString("event_title").orEmpty(),
                    sessionStart = data?.optString("session_start").orEmpty(),
                    seat = data?.optString("seat_identifier").orEmpty(),
                    customer = data?.optString("customer_name").orEmpty()
                )
                ScanResult(
                    accepted = json.optBoolean("success") && statusCode in 200..299,
                    duplicate = json.optBoolean("duplicate"),
                    message = json.optString("message", "Проверка завершена."),
                    details = details
                )
            }
        }
}
