import Foundation

struct TicketDetails: Decodable {
    let ticketUid: String?
    let eventTitle: String?
    let sessionStart: String?
    let seatIdentifier: String?
    let customerName: String?

    enum CodingKeys: String, CodingKey {
        case ticketUid = "ticket_uid"
        case eventTitle = "event_title"
        case sessionStart = "session_start"
        case seatIdentifier = "seat_identifier"
        case customerName = "customer_name"
    }
}

struct CheckinResponse: Decodable {
    let success: Bool?
    let duplicate: Bool?
    let message: String?
    let data: TicketDetails?
}

enum TicketAPIError: LocalizedError {
    case invalidResponse
    case loginFailed
    case csrfUnavailable

    var errorDescription: String? {
        switch self {
        case .invalidResponse:
            return "Сервер вернул некорректный ответ."
        case .loginFailed:
            return "Неверное имя пользователя или пароль."
        case .csrfUnavailable:
            return "Не удалось получить токен безопасности."
        }
    }
}

final class TicketAPI {
    private let baseURL = URL(string: "https://cabinet.zhassahna.kz/")!
    private let session: URLSession
    private var csrfToken = ""

    init() {
        let configuration = URLSessionConfiguration.default
        configuration.httpCookieStorage = HTTPCookieStorage.shared
        session = URLSession(configuration: configuration)
    }

    func login(username: String, password: String) async throws {
        let loginURL = baseURL.appendingPathComponent("login.php")
        var getRequest = URLRequest(url: loginURL)
        getRequest.httpMethod = "GET"
        let (htmlData, _) = try await session.data(for: getRequest)
        let html = String(decoding: htmlData, as: UTF8.self)
        csrfToken = try extractCSRFToken(from: html)

        var postRequest = URLRequest(url: loginURL)
        postRequest.httpMethod = "POST"
        postRequest.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        postRequest.httpBody = formData([
            "username": username,
            "password": password,
            "csrf_token": csrfToken
        ])

        let (_, response) = try await session.data(for: postRequest)
        guard let httpResponse = response as? HTTPURLResponse,
              httpResponse.url?.path != "/login.php" else {
            throw TicketAPIError.loginFailed
        }
    }

    func checkIn(code: String, deviceUid: String) async throws -> CheckinResponse {
        let url = baseURL.appendingPathComponent("ajax/ticket.php")
        var components = URLComponents(url: url, resolvingAgainstBaseURL: false)!
        components.queryItems = [URLQueryItem(name: "action", value: "checkin")]

        var request = URLRequest(url: components.url!)
        request.httpMethod = "POST"
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.httpBody = formData([
            "code": code,
            "device_uid": deviceUid,
            "csrf_token": csrfToken
        ])

        let (data, _) = try await session.data(for: request)
        return try JSONDecoder().decode(CheckinResponse.self, from: data)
    }

    private func extractCSRFToken(from html: String) throws -> String {
        let pattern = #"name=["']csrf_token["'][^>]*value=["']([^"']+)["']"#
        let regex = try NSRegularExpression(pattern: pattern, options: [.caseInsensitive])
        let range = NSRange(html.startIndex..<html.endIndex, in: html)
        guard let match = regex.firstMatch(in: html, options: [], range: range),
              let tokenRange = Range(match.range(at: 1), in: html) else {
            throw TicketAPIError.csrfUnavailable
        }
        return String(html[tokenRange])
    }

    private func formData(_ values: [String: String]) -> Data? {
        var components = URLComponents()
        components.queryItems = values.map { URLQueryItem(name: $0.key, value: $0.value) }
        return components.percentEncodedQuery?.data(using: .utf8)
    }
}
