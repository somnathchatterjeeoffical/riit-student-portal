# RIIT Student Portal - InfinityFree Deployment Guide

এই গাইডে InfinityFree ফ্রি হোস্টিংয়ে ডাটাবেস ও ফাইল ডিপ্লয় করার সম্পূর্ণ সহজ ও ধাপে ধাপে পদ্ধতি দেওয়া হলো।

---

## ধাপ ১: InfinityFree-তে MySQL ডাটাবেস তৈরি করা

1. [InfinityFree Client Area](https://app.infinityfree.com)-তে লগইন করুন।
2. আপনার তৈরি করা একাউন্টে ক্লিক করুন এবং **Control Panel** (cPanel / VistaPanel) খুলুন।
3. **Databases** সেকশনে গিয়ে **MySQL Databases**-এ ক্লিক করুন।
4. **Create New Database** বক্সে একটি নাম দিন (যেমন: `riit`) এবং **Create Database** বাটনে ক্লিক করুন।
5. এখন আপনি আপনার ডাটাবেসের সম্পূর্ণ নাম দেখতে পাবেন (যেমন: `if0_38123456_riit`)।

---

## ধাপ ২: InfinityFree MySQL ডিটেইলস সংগ্রহ করা

InfinityFree Client Area বা Control Panel-এর বাম দিকের প্যানেল থেকে নিচের ৪টি তথ্য নোট করে নিন:
1. **MySQL Hostname**: (যেমন: `sql300.infinityfree.com` অথবা `sql102.epizy.com`)
2. **MySQL Username**: (যেমন: `if0_38123456`)
3. **MySQL Password**: (InfinityFree একাউন্টের পাসওয়ার্ড - Client Area-তে "Show Password"-এ পাওয়া যায়)
4. **MySQL Database Name**: (যেমন: `if0_38123456_riit`)

---

## ধাপ ৩: `db.php` ফাইলে লাইভ ক্রেডেনশিয়াল আপডেট করা

আপনার প্রোজেক্টের `db.php` ফাইলটি ওপেন করুন (লাইন নং ২৬-৩০):

```php
} else {
    // -------------------------------------------------------------
    // INFINITYFREE / LIVE HOSTING CREDENTIALS
    // -------------------------------------------------------------
    $host = "sqlXXX.epizy.com";         // ধাপ ২ থেকে পাওয়া Hostname বসান
    $user = "if0_XXXXXXXX";              // আপনার Username বসান
    $pass = "YOUR_VHOST_PASSWORD";       // আপনার একাউন্ট পাসওয়ার্ড বসান
    $dbname = "if0_XXXXXXXX_riit";       // তৈরি করা ডাটাবেসের নাম বসান
```

*নোট: `db.php` লোকালহোস্ট এবং লাইভ সার্ভার অটোমেটিক শনাক্ত করতে পারে। তাই লোকালহোস্টে কোন পরিবর্তন করতে হবে না।*

---

## ধাপ ৪: phpMyAdmin-এ `schema.sql` ইমপোর্ট করা (ঐচ্ছিক কিন্তু সাজেস্টেড)

InfinityFree-তে টেবিল ও স্যাম্পল ডাটা ইমপোর্ট করতে:
1. Control Panel-এ গিয়ে **phpMyAdmin** অপশনে ক্লিক করুন।
2. আপনার তৈরি করা ডাটাবেসটির নামের পাশে **Connect** বা ক্লিক করে ডাটাবেসটি সিলেক্ট করুন।
3. উপরের মেনু থেকে **Import** ট্যাবে যান।
4. **Choose File**-এ ক্লিক করে আপনার প্রোজেক্ট ফোল্ডারের `schema.sql` ফাইলটি সিলেক্ট করুন।
5. নিচে নেমে **Import / Go** বাটনে ক্লিক করুন। `students` ও `admissions` টেবিলগুলো সফলভাবে তৈরি হয়ে যাবে।

*(নোট: আপনি যদি ইমপোর্ট নাও করেন, তবু প্রথমবার সাইট ভিজিট করলেই `db.php` স্বয়ংক্রিয়ভাবে প্রয়োজনীয় টেবিলগুলো তৈরি করে নেবে)*

---

## ধাপ ৫: ফাইলগুলো InfinityFree-তে আপলোড করা

1. Control Panel থেকে **Online File Manager** খুলুন (অথবা FileZilla FTP ব্যবহার করতে পারেন)।
2. **`htdocs/`** ফোল্ডারের ভেতরে প্রবেশ করুন। (কোন ফাইল `htdocs`-এর বাইরে রাখবেন না)।
3. সেখানে আপনার তৈরি করা `StudentPortal.zip` ফাইলটি আপলোড করুন এবং রাইট ক্লিক করে **Extract** করুন। অথবা সব ফাইল সরাসরি আপলোড করুন:
   - `index.php`
   - `style.css`
   - `register.php`
   - `dashboard.php`
   - `dashboard.css`
   - `application.php`
   - `application.css`
   - `db.php`
   - `riit_logo.jpg`
   - `uploads/` ফোল্ডার
4. `uploads/` ফোল্ডারের পারমিশন ৭৫৫ বা ৭৭৭ সেট করা রয়েছে কিনা নিশ্চিত করুন যাতে প্রোফাইল ছবি আপলোড হতে পারে।

---

## ধাপ ৬: লাইভ ওয়েবসাইট পরীক্ষা করা

আপনার ব্রাউজারে InfinityFree ডোমেইন বা সাবডোমেইনটি ওপেন করুন:
`http://yourdomain.great-site.net/`

- **লগইন টেস্ট:** 
  - আইডি: `Somnath211244100` (অথবা আপনার রেজিস্টার্ড আইডি)
  - পাসওয়ার্ড: `riit123`
- অথবা সরাসরি রেজিস্ট্রেশন বা ২০-স্টেপ ফর্ম পূরণ করে দেখুন। ডাটা সরাসরি আপনার InfinityFree MySQL ডাটাবেসে সেভ হবে!
