-- Three dummy reels. Media paths are relative to CDN_BASE_URL
-- (absolute https:// URLs are accepted as well).

INSERT INTO reels (slug, title, caption, poster_url, video_360_url, video_540_url, video_720_url, duration_seconds, sort_order, created_at, updated_at) VALUES
('brand-story-cafe',
 'روایت برند برای یک کافه محلی',
 'چطور با سه جمله‌ی کوتاه، داستان یک کافه‌ی کوچک را به محتوایی تبدیل کردیم که مخاطب دوست دارد ذخیره‌اش کند. کپشن خوب یعنی لحن درست، نه کلمات بیشتر.',
 'reels/brand-story-cafe/poster.webp',
 'reels/brand-story-cafe/360.mp4',
 'reels/brand-story-cafe/540.mp4',
 'reels/brand-story-cafe/720.mp4',
 18, 10, '2026-10-01T09:00:00Z', '2026-10-01T09:00:00Z'),
('hook-first-3-seconds',
 'سه ثانیه‌ی اول: قلاب ریلز',
 'بیشتر مخاطبان در همان سه ثانیه‌ی اول تصمیم می‌گیرند بمانند یا رد شوند. در این ریلز سه الگوی قلاب را که برای مشتریان‌مان جواب داده مرور می‌کنیم.',
 'reels/hook-first-3-seconds/poster.webp',
 'reels/hook-first-3-seconds/360.mp4',
 'reels/hook-first-3-seconds/540.mp4',
 'reels/hook-first-3-seconds/720.mp4',
 24, 20, '2026-10-05T09:00:00Z', '2026-10-05T09:00:00Z'),
('content-calendar-monthly',
 'تقویم محتوایی یک‌ماهه در ۳۰ ثانیه',
 'برنامه‌ریزی محتوا لازم نیست پیچیده باشد. یک جدول ساده، چهار ستون موضوعی و یک روز ثابت برای بازبینی؛ همین.',
 'reels/content-calendar-monthly/poster.webp',
 'reels/content-calendar-monthly/360.mp4',
 'reels/content-calendar-monthly/540.mp4',
 'reels/content-calendar-monthly/720.mp4',
 30, 30, '2026-10-10T09:00:00Z', '2026-10-10T09:00:00Z');
