-- #10725: a payment could get the GroupKey of a pledge for the same family,
-- fund and date, and delete and edit act on every row with that key. In each
-- group that holds both types, the payment rows move to the old key plus the
-- lowest plg_plgID among them. Generated keys have five parts, so the new key
-- matches no other group. The payment's cash counts move with it. Groups of
-- one type stay as they are, and so does a key too long to extend (no
-- generated key is). The derived table is materialized by its GROUP BY, which
-- lets MySQL read pledge_plg while updating it.

UPDATE `pledge_denominations_pdem` AS pdem
JOIN (
  SELECT `plg_GroupKey` AS old_key,
         CONCAT(`plg_GroupKey`, '|', MIN(IF(`plg_PledgeOrPayment` = 'Payment', `plg_plgID`, NULL))) AS new_key
  FROM `pledge_plg`
  GROUP BY `plg_GroupKey`
  HAVING SUM(`plg_PledgeOrPayment` = 'Pledge') > 0 AND SUM(`plg_PledgeOrPayment` = 'Payment') > 0
     AND CHAR_LENGTH(new_key) <= 64
) AS rekey ON rekey.old_key = pdem.`pdem_plg_GroupKey`
SET pdem.`pdem_plg_GroupKey` = rekey.new_key;

UPDATE `pledge_plg` AS plg
JOIN (
  SELECT `plg_GroupKey` AS old_key,
         CONCAT(`plg_GroupKey`, '|', MIN(IF(`plg_PledgeOrPayment` = 'Payment', `plg_plgID`, NULL))) AS new_key
  FROM `pledge_plg`
  GROUP BY `plg_GroupKey`
  HAVING SUM(`plg_PledgeOrPayment` = 'Pledge') > 0 AND SUM(`plg_PledgeOrPayment` = 'Payment') > 0
     AND CHAR_LENGTH(new_key) <= 64
) AS rekey ON rekey.old_key = plg.`plg_GroupKey`
SET plg.`plg_GroupKey` = rekey.new_key
WHERE plg.`plg_PledgeOrPayment` = 'Payment';
