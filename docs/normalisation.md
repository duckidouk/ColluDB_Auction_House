# ColluDB Auction House - Normalization Record

This document will record the normalization of each proposed relation. Relations should be examined individually, in the order 1NF, 2NF, then 3NF.

## Method

For every relation, record:

1. The relation and its attributes.
2. Business rules relevant to those attributes.
3. Candidate keys and the selected primary key.
4. Functional dependencies.
5. Whether the relation satisfies 1NF.
6. Whether the relation satisfies 2NF.
7. Whether the relation satisfies 3NF.
8. Any required decomposition and why it is lossless.

## Relation template

```text
Relation:

Business rules:

Candidate keys:

Primary key:

Functional dependencies:

1NF assessment:

2NF assessment:

3NF assessment:

Decomposition required:

Resulting relations:
```

## Progress

The first relation to examine will be `BIDS`:

```text
BIDS(Bid_ID, Buyer_ID, Auction_ID, Bid_Amount, Bid_Timestamp)
```

No normal-form conclusion has been recorded yet.

