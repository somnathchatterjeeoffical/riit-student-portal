# GitHub ও Render-এ পিএইচপি পোর্টাল লাইভ করার সম্পূর্ণ গাইড

বস, GitHub Pages শুধুমাত্র স্ট্যাটিক HTML/CSS সাপোর্ট করে, তাই পিএইচপি ফাইল সরাসরি সেখানে রান করে না। কিন্তু আপনার প্রোজেক্টটি **GitHub-এ পুশ করে Render (render.com)-এর মাধ্যমে ১ ক্লিকেই সম্পূর্ণ ফ্রিতে লাইভ** করা যায়। নিচে প্রতিটি ধাপ সহজভাবে দেওয়া হলো:

---

## ধাপ ১: GitHub-এ রিপোজিটরি তৈরি ও পুশ করা

১. [github.com](https://github.com)-এ গিয়ে **New Repository** তৈরি করুন (যেমন: `riit-student-portal`)।
২. আপনার কম্পিউটারে টার্মিনাল খুলে প্রোজেক্ট ফোল্ডারে গিট ইনিশিয়ালাইজ করে পুশ করুন:
```bash
cd c:\xampp\htdocs\PHP\StudentProtal
git init
git add .
git commit -m "Complete RIIT Portal with Teacher Dashboard, Session Gate & SweetAlert"
git branch -M main
git remote add origin https://github.com/আপনার-ইউজারনেম/riit-student-portal.git
git push -u origin main
```

---

## ধাপ ২: Render-এ অ্যাকাউন্ট ও Web Service তৈরি

১. [render.com](https://render.com)-এ যান এবং আপনার **GitHub একাউন্ট দিয়ে Sign In** করুন।
২. ড্যাশবোর্ডে গিয়ে **New +** বাটনে ক্লিক করে **Web Service** সিলেক্ট করুন।
৩. আপনার GitHub রিপোজিটরি (`riit-student-portal`) সিলেক্ট করে **Connect** বাটনে ক্লিক করুন।
৪. Render সেটিংস পেজে নিচের অপশনগুলো নির্বাচন করুন:
   - **Name:** `riit-portal` (বা আপনার পছন্দের নাম)
   - **Language / Environment:** `Docker` (আমরা ইতিমধ্যে প্রোজেক্টে `Dockerfile` যোগ করে দিয়েছি, তাই Render স্বয়ংক্রিয়ভাবে পিএইচপি ও MySQL ড্রাইভার সেটআপ করে নেবে)
   - **Instance Type:** `Free` ($0/month)
৫. নিচে নেমে **Create Web Service** বাটনে ক্লিক করুন।

---

## ধাপ ৩: ডাটাবেস সংযোগ (MySQL Cloud)

Render বিনামূল্যে ক্লাউড MySQL ডাটাবেস ব্যবহারের জন্য নিচের যেকোনো একটি ফ্রি ডাটাবেস ব্যবহার করতে পারেন:
- [Aiven.io](https://aiven.io) (Free MySQL) অথবা [TiDB Cloud](https://tidbcloud.com) (Free Serverless MySQL)
- অথবা Render Web Service-এর **Environment Variables**-এ আপনার ডাটাবেসের ডিটেইলস দিয়ে দিন:
  - `DB_HOST`: আপনার ক্লাউড হোস্ট
  - `DB_USER`: ইউজারনেম
  - `DB_PASS`: পাসওয়ার্ড
  - `DB_NAME`: ডাটাবেস নাম

---

## ধাপ ৪: লাইভ লিংক উপভোগ করুন!

বিল্ড সম্পন্ন হওয়া মাত্র Render আপনাকে একটি সুরক্ষিত লাইভ লিংক প্রদান করবে:
👉 `https://riit-portal.onrender.com/`

- লাইভ সাইটে স্টুডেন্ট ও টিচার উভয়ের সেশন সিকিউরিটি এবং SweetAlert2 পপআপ স্বয়ংক্রিয়ভাবে কাজ করবে।
