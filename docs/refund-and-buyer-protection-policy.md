# Refund, Return & Buyer Protection Policy

Internal reference — the business rules ChinaInNigeria works to. Vendors are Chinese-run
businesses already operating and stocking goods inside Nigeria, selling to Nigerian buyers in
Naira. All returns and deliveries are domestic (Nigeria to Nigeria), not international.

---

## 1. Refund policy — when a buyer returns a bad product

**The rule:** a buyer can report a problem within the return window after delivery (currently
configurable in admin Settings as `escrow_return_window_days`, e.g. 7 days).

**Who pays:**
- **Item damaged, wrong, or fake** → vendor pays return delivery, buyer gets a full refund.
- **Buyer just changed their mind** → buyer pays return delivery, or (recommended for low-cost
  goods) no return is offered at all for "changed my mind" cases — only for genuinely faulty
  items.

**How fast:**
- Vendor gets 48–72 hours to respond once a dispute is opened.
- No response, or the two sides can't agree → an admin steps in and makes the final call.

**If the vendor disputes it:**
- The vendor can respond with evidence (photos, packaging, delivery confirmation) inside the
  dispute.
- An admin reviews both sides and decides: refund, replace, or deny.
- This decision is final — it isn't left to keep going back and forth between buyer and vendor.

**Why this is safe for the platform, not just the buyer:**
The vendor's payout money is held in escrow and not released to them immediately after delivery
— it sits there until the return window passes with no open dispute. So a refund isn't "chasing
the vendor for cash back" — it's simply not releasing money that hasn't gone out yet, and sending
it back to the buyer instead. The platform is never out of pocket for a legitimate refund made
within the window.

---

## 2. Domestic returns — not the hard problem it first sounds like

Because every vendor is a real, Nigeria-based business (not a supplier shipping in from China per
order), returns are a **domestic Nigeria-to-Nigeria logistics problem**, not a cross-border one.
That means:

- No customs, no import duty, no international freight for a return.
- The only real question to settle is **who pays for return delivery**, covered in section 1
  above (vendor pays for their own mistake/fault; buyer pays for a change of mind, if allowed at
  all).
- Delivery tracking for the return trip should use the same tracking-number/carrier fields
  already built for the outbound delivery.

**Bottom line:** keep the return policy simple and domestic. Don't over-build for international
return logistics — it isn't the platform's problem to solve.

---

## 3. Buyer protection guarantee

**Recommendation: yes, offer one.** A clear promise — *"Your money is protected until you
confirm you're happy, or your return window passes"* — is a strong trust signal for a
still-new platform going up against established players (Jumia, Alibaba, Temu).

**Who actually pays for it:**
This guarantee costs the platform effectively nothing extra, because of how escrow already
works:
- The vendor's payout is held, not paid out immediately after delivery.
- The "guarantee" is really just *"we won't release the vendor's money if you have a genuine
  problem within the window"* — the platform is never paying out of its own funds, it's simply
  delaying/withholding a payout that hasn't happened yet.

**Where the real risk sits:**
The only genuine platform-side risk is a buyer complaining **after** the return window has
already closed and the vendor's money has been released. To keep this rare:
- Keep the return window length realistic (not too short to feel unfair, not so long that money
  sits in escrow forever).
- Communicate the window clearly to buyers at checkout and on the order page, so "you had until
  [date] to report a problem" is never a surprise.

**Practical wording to use publicly:** *"Your money stays protected until you confirm you're
happy — or your return window passes."*
