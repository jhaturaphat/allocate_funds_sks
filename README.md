# เกณฑ์การจ่ายค่าบริการทางการแพทย์ตามภาระเสี่ยง ปี ๒๕๖๖

ระบบนำเข้าสเตทเม้นประกันสังคมจากไฟล์ Excel และคำนวณยอดจัดสรรรายโรงพยาบาลตามคะแนนโรคเรื้อรังของแต่ละงวด สามารถตรวจสอบยอดบนหน้า Dashboard และส่งออกผลเป็น Excel ได้

## วิธีติดตั้งด้วย Docker Compose

เตรียม Git และ Docker ที่รองรับคำสั่ง `docker compose` แล้วเปิด Terminal หรือ PowerShell

### 1. ดาวน์โหลดโครงการ

```bash
git clone https://github.com/jhaturaphat/allocate_funds_sks.git
cd allocate_funds_sks
```

### 2. สร้าง PHP image และเริ่มบริการ

```bash
docker compose build php
docker compose up -d
```

### 3. ติดตั้งแพ็กเกจ Laravel

```bash
docker compose exec php composer install
```

สำหรับการติดตั้งครั้งแรก หาก Laravel ยังไม่มีไฟล์ `.env` ให้คัดลอก `.env.example` เป็น `.env` ในโฟลเดอร์เดียวกับ `artisan` แล้วตั้งค่าการเชื่อมต่อ MySQL ให้ตรงกับ Docker Compose หาก `APP_KEY` ยังว่าง ให้รัน `docker compose exec php php artisan key:generate`

### 4. สร้างตารางและนำเข้าคะแนนโรค

ตรวจว่า `DB_DATABASE` ชี้ไปยังฐานที่ต้องการ เช่น `sks` สำหรับฐานใหม่ ให้รันตามลำดับ

```bash
docker compose exec php php artisan config:clear
docker compose exec php php artisan migrate
docker compose exec php php artisan db:seed --class=DiseasesGroupSeeder
```

Seeder จะเพิ่มหรืออัปเดตคะแนนโรคเรื้อรัง 26 โรคในตาราง `diseases_group` โดยจับคู่จากรหัสโรค หากใช้ฐานเดิมที่เคยสร้างตารางผ่าน Navicat ให้ตรวจรายการด้วย `docker compose exec php php artisan migrate:status` ก่อนรัน migration

### 5. เปิดใช้งาน

หากตั้งพอร์ตเว็บไว้ที่ `8080` ให้เปิด [หน้า Dashboard](http://127.0.0.1:8080/) แล้วเลือก **นำเข้าไฟล์ Excel** เพื่อเริ่มทำงาน

## แก้ปัญหาสิทธิ์เขียนไฟล์

หาก Laravel แจ้ง `Permission denied` ใน `storage` หรือ `bootstrap/cache` ให้รันคำสั่งนี้จากโฟลเดอร์โครงการ เพื่อสร้างโฟลเดอร์ที่จำเป็นและให้ผู้ใช้ `www-data` เขียนไฟล์ได้

```bash
docker compose exec --user root php sh -c "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R u+rwX storage bootstrap/cache"
```

## ตรวจการเชื่อมต่อ MySQL

```bash
docker compose exec php php artisan db:show --database=mysql
```

หากเห็นชื่อฐาน `sks` และจำนวนตาราง แสดงว่า Laravel เชื่อมต่อ MySQL ได้แล้ว

### หาก db:show แจ้งว่า “The "intl" PHP extension is required”

PHP ใช้ส่วนขยาย `intl` เพื่อจัดรูปแบบตัวเลขในรายงาน สำหรับ Dockerfile ที่ใช้ `FROM php:8.3-fpm` ให้เพิ่ม `libicu-dev` ในรายการ `apt-get install` และเพิ่ม `intl` ในรายการ `docker-php-ext-install` ผู้ติดตั้งใหม่จะได้ส่วนขยายนี้จากขั้นตอน build ปกติเมื่อใช้ Dockerfile ที่ปรับแล้ว

สำหรับเครื่องที่ใช้ image เดิม หลังอัปเดต Dockerfile ให้สร้าง service PHP ใหม่และตรวจผล

```bash
docker compose up -d --build --no-deps php
docker compose exec php php --ri intl
docker compose exec php php artisan db:show --database=mysql
```

คำสั่งตรวจส่วนขยายควรแสดง `Internationalization support => enabled`
