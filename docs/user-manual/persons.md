# Persons

A Person record represents an individual in your congregation. Person records can be grouped into [Families](./families.md), belong to [Groups](./groups.md), have Properties assigned, and can be made users of the application.

> **Every person should belong to a family**, even if they are a "family of one."

---

## Finding a Person

Use the search bar in the top-left corner of the application. As you type, matching Person records appear in real time.

- The search is a **wildcard match** — it looks for your text anywhere in a first or last name
- Example: searching `ian` returns "Ian", "Brian", and anyone else whose name contains those letters
- Clicking a person's name opens their **Person View**, showing all information, properties, groups, and notes

> **Keyboard shortcut:** Press `?` from anywhere in ChurchCRM to open the search bar.

---

## Adding a New Person

**Option A — From the menu:**
1. Click **People → Add a New Person**
2. Complete the form
3. Click **Save** (or **Save and Add** to immediately add another person)

**Option B — From the Family Editor:**  
When adding a new family, you can add multiple family members at once from the same form. See [Families](./families.md).

---

## Person View — Red Text Explained

Some information on the Person View appears in **red text**. This means the information is inherited from the person's associated Family record.

For example, if the Smith family has a shared address, all family members show that address in red. If a member has their own address entered, it appears in black. See [Families — Inherited Information](./families.md#inherited-information-red-text) for more detail.

---

## Classifications

A Classification defines the person's relationship to the church — common examples are Member, Guest, Regular Attender, and Non-Attender.

Manage available classifications under **Admin → Manage Classifications**.

---

## Age

You do not enter a person's age directly. ChurchCRM calculates age automatically from the birth date you provide. At minimum, a birth year is needed.

---

## Custom Person Fields

Custom Person Fields let you add any information that doesn't come built-in with ChurchCRM.

Common examples:
- **Nickname / Preferred Name** — What members like to be called
- **Baptism or Confirmation Date** — Important spiritual milestones
- **T-Shirt Size** — Useful for event planning
- **Emergency Contact** — Important for youth ministry
- **Spiritual Gifts** — For ministry placement

See [Custom Fields](https://github.com/ChurchCRM/CRM/wiki/Custom-Fields) for step-by-step instructions on creating and managing custom fields.

---

## Deleting a Person

> Leaving old person records in the database doesn't hurt anything and supports historical record keeping. Consider deactivation instead of deletion.

1. Bring up the Person View
2. Click **Delete this Record**  
   _(If the link doesn't appear, you don't have delete permissions)_
3. Confirm the deletion

---

## Related Actions

| Task | See… |
|------|------|
| Add a person to the Cart | [Search & Cart](./search-and-cart.md) |
| Assign a person to a group | [Groups](./groups.md) |
| Track a person's donations | [Finances](./finances.md) |
| Add a note to a person | [Notes](https://github.com/ChurchCRM/CRM/wiki/Notes) |
| Assign properties to a person | [Properties](https://github.com/ChurchCRM/CRM/wiki/Properties) |

---

## Related

- [Families](./families.md) — Family records that persons belong to
- [Groups](./groups.md) — Groups and ministries
- [Search & Cart](./search-and-cart.md) — Finding and batch-processing people
