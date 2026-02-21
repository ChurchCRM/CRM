# Search & Cart

## Search

ChurchCRM has a powerful search bar that lets you quickly locate any record in your database.

### Opening Search

- The search bar is in the **top-left corner** of every page
- **Keyboard shortcut:** Press `?` from anywhere in the app to focus the search box

### What You Can Search

| Category | What's Searched | Notes |
|----------|----------------|-------|
| **People** | First and last names | Partial matches work |
| **Families** | Family names, custom properties | Partial matches work |
| **Groups** | Group names | Partial matches work |
| **Addresses** | Street addresses | Searches family addresses |
| **Calendar Events** | Title, description | |
| **Payments/Pledges** | Check numbers, amount ranges | Requires Finance permission |
| **Deposits** | Deposit comments | Requires Finance permission |

### Searching by Amount Range

To find payments within a specific dollar range, format your search as `min-max`:
- `100-500` → payments between $100 and $500
- `1000-5000` → payments between $1,000 and $5,000

> **Note:** Financial search results only appear for users with Finance permissions.

### Configuring Search Results

Administrators can control which result types appear and how many results show:

1. Go to **Admin → System Settings → Quick Search**
2. For each result type, enable/disable it or set a maximum number of results

---

## The Cart

The Cart is a temporary holding space for Person records. It lets you select a group of people, then act on them all at once — adding them to a group, sending them to a report, printing labels, and more.

- The Cart is **user-specific** — each user has their own cart
- The Cart is **session-specific** — it is cleared when you log out
- Placing someone in the Cart **does not change their record** in any way

### Checking Your Cart

The Cart icon in the top-right of the application shows a real-time count of how many people are in your cart. Click the icon to see a quick action menu or select **View Cart** to see all current cart members.

### Adding a Person to the Cart

**Method 1 — From the person list:**
1. Go to **People → View All Persons** (or search for a name)
2. Click **Add to Cart** next to the desired person

**Method 2 — From a person record:**
- Open the Person View and click **Add to Cart** within the record

**Method 3 — From a report:**
- Some reports include an **Add Results to Cart** button at the bottom — this adds everyone in the report results to your Cart at once

**Method 4 — From a group:**
- Open a Group View and click **Add Group Members to Cart**

### Removing a Person from the Cart

1. Click the Cart icon → **View Cart**
2. Find the person and click **Remove**

To remove **everyone**:
- Click **Empty Cart** at the bottom of the Cart list  
  _(This is different from **Empty Cart to Group** — it simply clears the Cart without moving people anywhere)_

### Cart Actions

Once you have people in the Cart, you can:

| Action | Where to Find It |
|--------|-----------------|
| Add all cart members to a group | **Cart → Empty Cart to Group** |
| Add all cart members to an event | **Cart → Empty Cart to Event** |
| Send an email to cart members | **Cart → Email Cart** |
| Print mailing labels | **Cart → Print Labels** |
| Generate a report | **Cart → Export/Report** options |

---

## Related

- [Persons](./persons.md) — Adding individuals to the Cart
- [Groups](./groups.md) — Adding Cart members to a group
- [Events](./events.md) — Adding Cart members to an event
