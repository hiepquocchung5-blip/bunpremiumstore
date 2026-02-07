ScottSub Project Structure

Root Directory (scottsub/)
│
├── index.php                  # Main Entry Point (Router)
├── .htaccess                  # URL Rewriting (Optional)
│
├── assets/                    # Static Assets
│   ├── css/
│   │   └── style.css          # Custom Glassmorphism Styles
│   ├── js/
│   │   └── app.js             # Global JS (CSRF, Timers)
│   └── images/
│
├── includes/                  # Core System Files
│   ├── config.php             # Database Connection & Constants
│   ├── functions.php          # Helper Functions (Auth, CSRF, Formatting)
│   ├── header.php             # HTML Header & Navigation
│   └── footer.php             # HTML Footer
│
├── modules/                   # Front-End Pages (User Side)
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   └── logout.php
│   ├── home/
│   │   └── index.php          # Landing Page / Dashboard
│   ├── shop/
│   │   ├── category.php
│   │   └── checkout.php       # Product Buying Logic
│   └── user/
│       ├── orders.php         # Order History & Chat
│       └── agent.php          # Agent/Reseller Hub
│
├── admin/                     # Admin Portal (Separate System)
│   ├── index.php              # Admin Dashboard
│   ├── login.php
│   └── modules/               # Admin Specific Modules
│       ├── products.php
│       └── orders.php
│
└── uploads/                   # User Uploads
└── proofs/                # Payment Screenshots