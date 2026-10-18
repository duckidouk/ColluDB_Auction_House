# ColluDB Auction House

ColluDB Auction House is a group database project for **COMP0178: Database Fundamentals** at University College London (UCL). The project explores how a relational database can support the main activities of an online auction platform.

The principal aim is to design, normalize, implement, and query a well-structured database. The website will provide a way to demonstrate that database functionality, but visual polish is not the main focus of the project.

## Project scope

The proposed system is intended to support:

- user accounts acting as buyers and/or sellers;
- items, images, and hierarchical categories;
- timed auctions with starting, reserve, and buy-now prices;
- bids and auction outcomes;
- watched auctions and favourite sellers;
- user sessions and activity logging;
- configurable notifications;
- complaints and post-auction feedback.

These capabilities represent the current design direction. They should not yet be treated as implemented features.

## Current status

The project is currently in the **conceptual and logical database-design stage**.

Completed so far:

- an initial UML-style ER diagram;
- a draft translation of the ER model into relational schemas;
- a structured record for evaluating the relations against 1NF, 2NF, and 3NF.

Still to be completed:

- confirmation of the remaining business rules;
- normalization of each proposed relation;
- physical SQL design and constraints;
- database implementation and sample data;
- backend and frontend development;
- testing and demonstration material.

No SQL schema or application code has been implemented yet.

## Repository structure

```text
ColluDB_Auction_House/
├── README.md
├── database/                  # SQL implementation will be added later
├── diagrams/
│   └── DBMS ER diagram_V1 (UML notation).svg
└── docs/
    ├── normalization.md       # normalization method and evidence
    ├── relational-schema.md   # logical relational translation
    └── reference/
        └── Project_Introduction.pdf
```

The `database/` directory is intentionally empty while the logical schema and normalization decisions are being validated.

## Design workflow

The project follows this sequence:

1. Establish the system requirements and business rules.
2. Maintain the conceptual ER model.
3. Translate the model into relational schemas.
4. Identify candidate keys and functional dependencies.
5. Evaluate each relation against 1NF, 2NF, and 3NF.
6. Decompose relations only when a normal-form violation exists.
7. Translate the validated logical design into SQL.
8. Add constraints, representative data, queries, and application functionality.
9. Test the database against the required auction-house operations.

## Intended technology

The anticipated teaching stack is:

- MySQL or MariaDB;
- PHP for server-side database interaction;
- Apache through XAMPP, MAMP, or an equivalent local environment;
- HTML, CSS, and JavaScript for the website interface;
- Git and GitHub for group version control.

The final technology choices remain subject to the coursework specification and agreement within the group.

## Open design questions

Important decisions still under review include:

- whether an unsold item can be relisted in multiple auctions;
- how one account can participate as both buyer and seller;
- whether storing both a winning bid and its buyer in an outcome introduces redundancy;
- which statuses and descriptive types should use controlled lookup relations;
- how optional event-specific information should be represented in notifications and activity records.

These decisions must be resolved from explicit business rules before the corresponding relations can be considered final.

## Academic context

This repository contains assessed group-project work. Contributions should be understood by the team, reviewed before integration, and attributed where they incorporate external material or AI assistance. The definitive requirements and submission instructions are provided through the COMP0178 coursework specification on Moodle.
