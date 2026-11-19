# تقرير فني: دعم التبرع العيني ومندوب الاستلام (حملة «مركون»)

**التاريخ:** 18 نوفمبر 2026 · **موجّه إلى:** الليدر وفريق الفرونت إند
**الديبلوي:** `deploy-history/deploy-2026-11-18a-in-kind-donations`
**نسخة Word من التقرير:** [`2026-11-18-in-kind-donation-report.docx`](2026-11-18-in-kind-donation-report.docx)

## 1. الملخص
عند التبرع العيني (أثاث، أجهزة، ملابس، أواني) كان بيظهر الخطأ «مبلغ التبرع يجب ألا يقل عن 1 جنيه». السبب إن إجمالي التبرع العيني 0 ج.م، والباك كان بيرفض أي `amount` أقل من 1، ومكانش فيه أي دعم للتبرع العيني. اتعدّل الباك، ومطلوب تعديل بسيط في الفرونت عشان يشتغل.

## 2. السبب
- الفرونت بيبعت التبرع العيني بـ `amount = 0` أو من غير `amount`، من غير ما يقول للباك إنه تبرع عيني.
- الباك مكانش بيفرق بين العيني والنقدي، فطبّق `min:1` على الكل.
- نوع الحاجة المتبرع بيها (أثاث، ملابس...) مكانش بيتخزن في أي مكان.
- بيانات المندوب (المحافظة، المدينة، العنوان، الوقت) كانت موجودة بالفعل في الباك وبتتخزن وبترجع في `pickup`.

## 3. التعديلات في الباك
| الملف | التعديل |
|---|---|
| `database/migrations/2026_11_18_100000_add_in_kind_fields_to_donations_table.php` (جديد) | 3 أعمدة في `donations`: `is_in_kind` و`in_kind_category` و`in_kind_description` (آمن لو اتشغل مرتين) |
| `app/Models/Donation.php` | الحقول الجديدة في `fillable` + cast لـ `is_in_kind` كـ boolean |
| `app/Http/Requests/Donation/StoreDonationRequest.php` | قواعد الفاليديشن الجديدة (الفقرة 4) |
| `app/Http/Resources/DonationResource.php` | رجوع الحقول الجديدة في الـ response |

**التشغيل على السيرفر:** من فولدر الديبلوي `bash deploy.sh --check` ثم `bash deploy.sh`
(أو يدوياً: `php artisan migrate` ثم `php artisan optimize:clear`).

## 4. قواعد الفاليديشن الجديدة
1. `is_in_kind` (boolean): لو `true` يبقى `amount` بيتحط 0 تلقائياً والحد الأدنى بيبقى 0.
2. `in_kind_category`: مطلوب لو `is_in_kind = true` (نص، حد أقصى 100 حرف).
3. `in_kind_description`: اختياري (حد أقصى 1000 حرف).
4. التبرع العيني لازم يكون بطريقة المندوب فقط (`type = cash_pickup`)، وأي طريقة تانية بترجع 422.
5. لو الطريقة مندوب: `pickup_governorate` و`pickup_city` و`pickup_address` مطلوبين (`pickup_city` كان اختياري قبل كده)، و`pickup_preferred_time` اختياري.
6. التبرع النقدي العادي زي ما هو (`amount` أقل حاجة 1).

## 5. المطلوب من الفرونت (Checkout)
`POST /api/v1/donations/submit` بـ `multipart/form-data`، وعلى تاب «تبرع عيني»:

```js
const fd = new FormData();
fd.append('phone', phone);
fd.append('donation_campaign_id', campaignId);
fd.append('payment_method_id', pickupMethodId); // type = cash_pickup

// NEW: in-kind fields
fd.append('is_in_kind', 'true');          // required, or the error stays
fd.append('in_kind_category', category);  // required for in-kind
fd.append('in_kind_description', desc);   // optional

// pickup agent data
fd.append('pickup_governorate', governorate);
fd.append('pickup_city', city);
fd.append('pickup_address', address);
fd.append('pickup_preferred_time', preferredTime);

// do NOT send amount
```

**قيم `in_kind_category` المقترحة** (الباك بيقبل أي نص، بس لازم الفريق يتفق عليها):

| القيمة | العرض |
|---|---|
| `furniture` | أثاث وصالونات |
| `appliances` | أجهزة كهربائية |
| `clothes` | ملابس ومفروشات |
| `kitchenware` | أواني ومستلزمات |

**تعديلات الواجهة:**
1. التبرع العيني يعرض طريقة «مندوب تحصيل البيت» فقط ويخفي باقي الطرق (الباك هيرفض أي طريقة تانية).
2. مفيش خانة مبلغ ولا رسالة «0 ج.م» في التبرع العيني.
3. عرض أخطاء الفاليديشن (422) حقل بحقل من الـ response.
4. لو التبرع نقدي عن طريق المندوب، لازم يبعت `pickup_city` برضه.

## 6. تعديلات الداشبورد
الـ response بيرجع في كل تبرع:

```json
"amount": "0.00",
"is_in_kind": true,
"in_kind_category": "furniture",
"in_kind_description": "sofa and table",
"pickup": {
  "governorate": "Dakahlia",
  "city": "Mansoura - University District",
  "address": "Street 1, Building 5",
  "preferred_time": "Evening (4 - 9)"
}
```
- بادج «تبرع عيني» لما `is_in_kind = true`.
- عرض النوع والوصف وبيانات المندوب في تفاصيل التبرع.
- عدم عرض «0 ج.م» للتبرع العيني (يتعرض بدلها نوع الحاجة).

## 7. الأخطاء المتوقعة (422)
| الحالة | الحقل |
|---|---|
| عيني بدون `in_kind_category` | `in_kind_category` |
| عيني بطريقة دفع غير المندوب | `payment_method_id` |
| مندوب بدون محافظة أو مدينة أو عنوان | الحقل الناقص |
| نقدي بمبلغ أقل من 1 | `amount` |

## 8. خطوات الاختبار
1. تأكد إن الـ migration اتشغلت على السيرفر.
2. جرّب في Postman بالحقول اللي في الفقرة 5. المتوقع: كود 201، وقيمة `amount` تساوي `0.00`، وقيمة `is_in_kind` تساوي `true`.
3. شيل `pickup_address` وتأكد إنه يرجع 422.
4. جرّب نفس الطلب بطريقة دفع بنكي، لازم يرجع 422.
5. بعد كده الفرونت يجرب من الواجهة.

## 9. ملاحظات للفريق
- الإيميلات (`app/Support/Mail`) و`NotifyDonor` ممكن تكتب «بمبلغ 0 جنيه» للتبرع العيني، وده محتاج تعديل لاحق.
- تعديلات الباك اتكتبت من غير تشغيل PHP محلياً، فلازم تتجرب على بيئة اختبار أو بعد أخذ نسخة احتياطية قبل الإنتاج.
