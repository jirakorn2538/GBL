# 💰 Money Life: เกมจำลองการวางแผนการเงินในชีวิตประจำวัน

เว็บแอปพลิเคชันเพื่อการเรียนรู้การวางแผนการเงินสำหรับนักเรียนชั้นมัธยมศึกษาตอนต้น พร้อมระบบ Dashboard และสถิติสำหรับครูผู้สอน/ผู้ดูแลระบบ (Admin)

---

## 🚀 ขั้นตอนการนำขึ้นออนไลน์ (Deployment Guide)

### 1️⃣ นำเข้าฐานข้อมูลใน Supabase (Database Setup)
โครงการนี้รองรับฐานข้อมูล **Supabase (PostgreSQL)** โดยมีไฟล์ `supabase_schema.sql` พร้อมใช้งาน:
1. เข้าสู่ Supabase Dashboard ของคุณ: [jirakorn2538's Project](https://supabase.com/dashboard/project/fsndkjviwlxavydglxcw)
2. ไปที่เมนู **SQL Editor** ที่แถบเมนูด้านซ้าย
3. กดปุ่ม **+ New Query**
4. เปิดไฟล์ `supabase_schema.sql` ในโปรเจกต์ คัดลอกโค้ดทั้งหมดมาวางในช่อง SQL Editor
5. กดปุ่ม **Run** (สีเขียว) ด้านขวาล่าง
6. ฐานข้อมูลจะสร้างตาราง `students`, `game_sessions`, `game_answers`, `teachers`, `audit_logs` พร้อมบัญชีผู้ดูแลระบบ (Admin) ให้อัตโนมัติ:
   - **Username (เลข 13 หลัก):** `1329900585149`
   - **Password (เบอร์โทร):** `0873565288`

---

### 2️⃣ คัดลอก Connection String จาก Supabase
1. ในหน้า Supabase Dashboard ไปที่ **Project Settings** (ไอคอนฟันเฟือง) ด้านล่างซ้าย
2. เลือกเมนู **Database**
3. เลื่อนลงมาที่หัวข้อ **Connection string**
4. เลือกแท็บ **URI**
5. คัดลอก URL ซึ่งจะมีลักษณะดังนี้:
   ```text
   postgresql://postgres.fsndkjviwlxavydglxcw:[YOUR-PASSWORD]@aws-0-ap-southeast-1.pooler.supabase.com:6543/postgres
   ```
   *(อย่าลืมแทนที่ `[YOUR-PASSWORD]` ด้วยรหัสผ่านฐานข้อมูล Supabase ที่คุณตั้งไว้ตอนสร้างโปรเจกต์)*

---

### 3️⃣ อัปโหลดโค้ดขึ้น GitHub (`jirakorn2538/GBL`)
นำไฟล์ทั้งหมดในโฟลเดอร์นี้ขึ้น GitHub Repository [jirakorn2538/GBL](https://github.com/jirakorn2538/GBL):

#### วิธีที่ 1: ผ่าน Git Command Line (หากมี Git ติดตั้งในเครื่อง)
```bash
git init
git remote add origin https://github.com/jirakorn2538/GBL.git
git add .
git commit -m "Deploy Money Life to GitHub"
git branch -M main
git push -u origin main
```

#### วิธีที่ 2: ผ่าน GitHub Web หรือ GitHub Desktop
- ลากไฟล์ทั้งหมดในโฟลเดอร์นี้อัปโหลดขึ้น Repository `jirakorn2538/GBL` บนหน้าเว็บ GitHub ได้ทันที

---

### 4️⃣ เชื่อมต่อและ Deploy บน Vercel
1. ไปที่ลิงก์ที่คุณเปิดไว้: [New Project – Vercel](https://vercel.com/new/import?framework=other&id=1383141251&name=GBL&owner=jirakorn2538&project-name=gbl&provider=github&s=https%3A%2F%2Fgithub.com%2Fjirakorn2538%2FGBL&teamSlug=jirakorn)
2. เลือก Repository **jirakorn2538/GBL**
3. ในหน้า **Configure Project**:
   - **Framework Preset:** เลือก `Other`
   - ขยายหัวข้อ **Environment Variables** แล้วเพิ่มตัวแปร:
     - **Key:** `DATABASE_URL`
     - **Value:** วาง Connection URI จาก Supabase ในขั้นตอนที่ 2
4. กดปุ่ม **Deploy**
5. รอ Vercel ประมวลผลประมาณ 1-2 นาที คุณจะได้ URL เว็บไซต์พร้อมออนไลน์ทันที! 🎉
