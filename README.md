# 🚀 NovaPHP

**A modern, lightweight, open-source PHP framework built from scratch.**

NovaPHP is an independent PHP framework designed to simplify the development of modern web applications and RESTful APIs. Inspired by the simplicity and developer experience of modern PHP frameworks, NovaPHP focuses on clean architecture, flexibility, performance, and ease of use.

Built with PHP 8.2+, NovaPHP aims to provide developers with the essential tools needed to build scalable PHP applications while maintaining a lightweight and understandable codebase.

> [!WARNING]
> 🚧 **NovaPHP is currently in early development and is not recommended for production use.**
>
> This project is under active development. Features, APIs, and architecture may change as the framework evolves.

## ✨ Features

NovaPHP Phase 1 includes the following features:

- **MVC Architecture:** Organize application logic using models, views, and controllers.
- **Routing System:** Support for GET, POST, PUT, PATCH, and DELETE HTTP methods.
- **Dynamic Routes:** Define routes with named URL parameters.
- **HTTP Handling:** Parse incoming requests and return HTML, text, or JSON responses.
- **REST API Support:** Build JSON-based API endpoints.
- **Controllers:** Support class-based controllers and closure route handlers.
- **Dependency Handling:** Automatically inject HTTP Request objects into route handlers.
- **Autoloading:** PSR-4-style namespace autoloading with optional Composer integration.
- **Error Handling:** Built-in 404, 405, and exception handling.
- **Command-Line Interface:** Manage your application through the `nova` CLI.
- **Testing:** Standalone automated tests.

> **Note:** These are Phase 1 capabilities. Advanced features such as database models, middleware, and full dependency injection are planned for later releases.

## 📋 Requirements

- PHP 8.2 or higher
- Composer (optional for Phase 1)
- Git (recommended)

No external runtime packages are required for Phase 1.

## 📦 Installation

### 1. Clone the repository

```bash
git clone https://github.com/Kwenziwa/novaphp.git

cd novaphp
```

### 2. Configure autoloading (optional)

NovaPHP includes a built-in PSR-4-style fallback autoloader, so Composer is not required to run Phase 1.

If Composer is installed, generate the standard autoloader:

```bash
composer dump-autoload
```

### 3. Start the development server

```bash
php nova serve
```

Open your browser and visit:

**http://127.0.0.1:8000**

Your NovaPHP application should now be running.

## 🛠️ NovaPHP CLI

NovaPHP comes with a lightweight command-line interface.

### Start the development server

```bash
php nova serve
```

### List registered routes

```bash
php nova route:list
```

Additional CLI commands will be introduced in future releases.

## 🌐 Routing

NovaPHP supports multiple HTTP methods and dynamic URL parameters.

Example:

```php
use Nova\Routing\Router;

return static function (Router $router): void {
    $router->get('/', function () {
        return 'Welcome to NovaPHP!';
    });

    $router->get('/hello/{name}', function ($name) {
        return "Hello, {$name}!";
    });
};
```

Visit `/hello/John` to receive:

```text
Hello, John!
```

## 🔌 REST API

NovaPHP provides built-in support for JSON requests and responses.

### Health check endpoint

```bash
curl http://127.0.0.1:8000/api/health
```

### POST request example

```bash
curl -X POST http://127.0.0.1:8000/api/echo \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello"}'
```

NovaPHP can parse JSON payloads and return structured JSON responses.

## 🧪 Testing

NovaPHP includes standalone automated tests.

Run the test suite:

```bash
php tests/run.php
```

These tests help verify the functionality of core framework components.

## 🏗️ Framework Architecture

NovaPHP is being developed with a modular architecture to keep the framework organized and maintainable.

```text
novaphp/
├── app/
│   └── Controllers/
├── bootstrap/
├── config/
├── public/
├── resources/
├── routes/
│   ├── web.php
│   └── api.php
├── src/
│   ├── Core/
│   ├── Http/
│   └── Routing/
├── tests/
├── composer.json
└── nova
```

The framework separates application code from its internal components, allowing developers to extend functionality as NovaPHP evolves.

## 🗺️ Development Roadmap

NovaPHP is being developed in phases.

### ✅ Phase 1: Core Foundation

- [x] Framework bootstrap
- [x] Namespace autoloading
- [x] HTTP request and response handling
- [x] Routing system
- [x] Dynamic route parameters
- [x] Controller support
- [x] JSON API responses
- [x] Exception and error handling
- [x] Basic CLI
- [x] Automated tests

### ⏳ Phase 2: Framework Architecture

- [ ] Dependency injection container
- [ ] Service providers
- [ ] Configuration management
- [ ] Application lifecycle improvements
- [ ] Enhanced CLI commands

### ⏳ Phase 3: Database Layer

- [ ] Database connection management
- [ ] Query builder
- [ ] ORM
- [ ] Database migrations
- [ ] Database seeders
- [ ] Model relationships

### ⏳ Phase 4: Web Application Features

- [ ] Middleware
- [ ] Authentication
- [ ] Session management
- [ ] CSRF protection
- [ ] Request validation
- [ ] Template engine

### ⏳ Phase 5: Developer Experience

- [ ] Model and controller generators
- [ ] Advanced CLI tooling
- [ ] Logging
- [ ] Caching
- [ ] Queue system
- [ ] Improved testing utilities

### ⏳ Phase 6: Ecosystem and Distribution

- [ ] Composer distribution
- [ ] Project installer
- [ ] Package system
- [ ] Official documentation
- [ ] Release versioning
- [ ] Community contributions

## 🔐 Security

NovaPHP is currently an experimental framework and does not yet provide a complete production security model.

The following features are not yet implemented:

- Authentication and authorization
- CSRF protection
- Security middleware
- Session security controls
- Comprehensive request validation
- Production security hardening

For development, exception details can be enabled using:

```env
APP_DEBUG=true
```

**Never enable debugging in a public production environment.**

For deployment, disable debugging and configure the web server document root to point to `public/`.

## 🤝 Contributing

Contributions, suggestions, and feedback are welcome.

If you would like to contribute:

1. Fork the repository.
2. Create a feature branch.
3. Make your changes.
4. Add or update relevant tests.
5. Submit a pull request.

As NovaPHP is in early development, please open an issue before proposing major architectural changes.

## 📄 License

NovaPHP is intended to be released as open-source software under the **MIT License**.

See the `LICENSE` file for the full license terms once it has been added to the repository.

## 👨‍💻 Author

**Kwenziwa Khanyile**

Software Developer | PHP & Full-Stack Development

GitHub: [@Kwenziwa](https://github.com/Kwenziwa)

## ⭐ Support NovaPHP

If you find NovaPHP interesting, consider giving the repository a star on GitHub.

Your support helps encourage the continued development of the framework.

---

**NovaPHP: Build Beyond the Framework.** 🚀

*Built from scratch. Designed for developers.*