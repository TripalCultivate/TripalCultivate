CREATE TABLE IF NOT EXISTS biomaterial_project (
    biomaterial_project_id bigserial primary key NOT NULL,
    biomaterial_id bigint NOT NULL,
    project_id bigint NOT NULL,
    CONSTRAINT biomaterial_project_c1 UNIQUE (biomaterial_id, project_id),
    FOREIGN KEY (biomaterial_id) REFERENCES biomaterial(biomaterial_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES project(project_id) ON DELETE CASCADE
);

CREATE INDEX  biomaterial_project_idx1 ON biomaterial_project USING btree (biomaterial_id);
CREATE INDEX  biomaterial_project_idx2 ON biomaterial_project USING btree (project_id);

COMMENT ON TABLE project_stock IS 'This table is intended associate records in the biomaterial table with a project.';


-- ================================================
-- TABLE: stock_biomaterial
-- ================================================
CREATE TABLE IF NOT EXISTS stock_biomaterial (
    stock_biomaterial_id bigserial primary key NOT NULL,
    biomaterial_id bigint NOT NULL,
    stock_id bigint NOT NULL,
    CONSTRAINT stock_biomaterial_c1 UNIQUE (biomaterial_id, stock_id),
    FOREIGN KEY (biomaterial_id) REFERENCES biomaterial(biomaterial_id) ON DELETE CASCADE,
    FOREIGN KEY (stock_id) REFERENCES stock(stock_id) ON DELETE CASCADE
);

CREATE INDEX  stock_biomaterial_idx1 ON stock_biomaterial USING btree (biomaterial_id);
CREATE INDEX  stock_biomaterial_idx2 ON stock_biomaterial USING btree (stock_id);

COMMENT ON TABLE stock_biomaterial IS 'Associates records in the biomaterial table with a stock.';
create table db_relationship (
    db_relationship_id bigserial not null,
    type_id bigint not null,
    subject_id bigint not null,
    object_id bigint not null,
    primary key (db_relationship_id),
    foreign key (type_id) references db (db_id) on delete cascade INITIALLY DEFERRED,
    foreign key (subject_id) references db (db_id) on delete cascade INITIALLY DEFERRED,
    foreign key (object_id) references db (db_id) on delete cascade INITIALLY DEFERRED,
    constraint db_relationship_c1 unique (subject_id,object_id,type_id)
);
create index db_relationship_idx1 on db_relationship USING btree (type_id);
create index db_relationship_idx2 on db_relationship USING btree (subject_id);
create index db_relationship_idx3 on db_relationship USING btree (object_id);

COMMENT ON TABLE db_relationship IS 'Specifies relationships between databases.  This is
particularly useful for ontologies that use multiple prefix IDs for its vocabularies. For example,
the EDAM ontology uses the prefixes "data", "format", "operation" and others. Each of these would
have a record in the db table.  An "EDAM" record could be added for the entire ontology to the
db table and the previous records could be linked as "part_of" EDAM.  As another example
databases housing cross-references may have sub databases such as NCBI (e.g. Taxonomy, SRA, etc).
This table can use a "part_of" record to link all of them to NCBI.';
ALTER TABLE analysis
  DROP CONSTRAINT analysis_c1
, ADD  CONSTRAINT analysis_c1 unique (program,programversion, name, sourcename);
/* https://github.com/GMOD/Chado/issues/70 */
CREATE INDEX cvtermsynonym_idx2 ON cvtermsynonym (type_id);
CREATE INDEX cvtermsynonym_idx3 ON cvtermsynonym (synonym);
/* https://github.com/GMOD/Chado/issues/37 */
ALTER TABLE project
ADD COLUMN type_id bigint;
ALTER TABLE project ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
CREATE INDEX project_idx1 ON project USING btree (type_id);
COMMENT ON COLUMN project.type_id IS 'An optional cvterm_id that specifies what type of project this record is.  Prior to 1.4, project type was set with an projectprop.';
/* rewriting issue https://github.com/GMOD/Chado/pull/50 */

ALTER TABLE assay
ALTER COLUMN arraydesign_id
DROP NOT null;

COMMENT ON TABLE assay IS 'An assay consists of an experiment for a single biosample, for example a microarray or an RNASeq library sequence set. An assay can be thought of as an experiment to quantify expression of a sample.';

COMMENT ON TABLE acquisition IS 'This represents the acquisition technique. In the case of a microarray, it is scanning, in the case of a sequencer, it is sequencing. The output of this process is a digital image of an array for a microarray or a set of digital images or nucleotide base calls for a sequencer.';

COMMENT ON TABLE quantification IS 'Quantification is the transformation of an image or set of sequences to numeric expression data. This typically involves statistical procedures.';

ALTER TABLE element
ALTER COLUMN arraydesign_id
DROP NOT null;

COMMENT ON TABLE element IS 'For a microarray, represents a feature of the array. This is typically a region of the array coated or bound to DNA. For RNASeq, represents a feature sequence that is used for aligning and quantifying reads.';

COMMENT ON TABLE elementresult IS 'Expression signal. In the case of a microarray, the hybridization signal. In the case of RNAseq, the read count. May be normalized or raw, as specified in the acquisition record.';
ALTER TABLE cell_line ADD COLUMN is_obsolete boolean DEFAULT false;
--I dont believe there is an "ALTER TABLE" command for a comment, you just "redo" the comment as it is here
COMMENT ON TABLE analysis IS 'An analysis is a particular type of a computational analysis; it may be a blast of one sequence against another, or an all by all blast, or a different kind of analysis altogether. It can be a single unit of computation or an analysis that represents a set of computational steps. For example, a reference genome assembly could be stored as a single record in the analysis table, but this analysis could encompass many individual analysis steps (read trimming, assembly, scaffolding, etc). These could be stored and related via the analysis_relationship table.';
/* Delete the improperly added foreign key from v1.3.3.002 */
ALTER TABLE db_relationship DROP CONSTRAINT db_relationship_type_id_fkey;
/* Add it back in with type_id => cvterm.cvterm_id as it should have */
ALTER TABLE db_relationship ADD CONSTRAINT db_relationship_type_id_fkey FOREIGN KEY  (type_id) REFERENCES cvterm(cvterm_id) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED;
/* https://github.com/GMOD/Chado/issues/140 */
/* Upgrade unique constraints with nullable columns if PostgreSQL version > 15 */
CREATE OR REPLACE PROCEDURE addUniqueLinkerConstraint(table_name varchar, constraint_name varchar, columns varchar[])
  LANGUAGE plpgsql
  AS $$
  DECLARE
    newer_than_15 boolean;
  BEGIN
    -- Determine the version of PostgreSQL
    SELECT CASE WHEN current_setting('server_version_num')::INT > 150000 THEN true ELSE false END AS supported INTO newer_than_15;

    -- IF the version is newer then we can use the new UNIQUE NULLS NOT DISTINCT
    -- which does not treat 2 records that are the same but include NULL as distict.
    IF newer_than_15 THEN
      EXECUTE format('ALTER TABLE %s ADD CONSTRAINT %s UNIQUE NULLS NOT DISTINCT (%s)', table_name, constraint_name, array_to_string(columns, ','));
    -- IF the version is <15 then we use the original UNIQUE style constraint
    ELSE
      EXECUTE format('ALTER TABLE %s ADD CONSTRAINT %s UNIQUE (%s)', table_name, constraint_name, array_to_string(columns, ','));
    END IF;
  END
$$;
/* Contact Linkers */
/* -- Feature */
ALTER TABLE feature_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN feature_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Curator (NCIT:C69141) indicates that the linked contact curated a particular gene model.';
ALTER TABLE feature_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN feature_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique feature - contact combination.';
ALTER TABLE feature_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE feature_contact DROP CONSTRAINT feature_contact_c1;
CALL addUniqueLinkerConstraint('feature_contact', 'feature_contact_c1', ARRAY['feature_id', 'contact_id', 'type_id']);
/* -- Featuremap */
ALTER TABLE featuremap_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN featuremap_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Curator (NCIT:C69141) indicates that the linked contact curated this genetic map.';
ALTER TABLE featuremap_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN featuremap_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique featuremap - contact combination.';
ALTER TABLE featuremap_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE featuremap_contact DROP CONSTRAINT featuremap_contact_c1;
CALL addUniqueLinkerConstraint('featuremap_contact', 'featuremap_contact_c1', ARRAY['featuremap_id', 'contact_id', 'type_id']);
/* -- Library */
ALTER TABLE library_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN library_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Distributor (NCIT:C48289) indicates that the linked contact organization distributes this library.';
ALTER TABLE library_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN library_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique library - contact combination.';
ALTER TABLE library_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE library_contact DROP CONSTRAINT library_contact_c1;
CALL addUniqueLinkerConstraint('library_contact', 'library_contact_c1', ARRAY['library_id', 'contact_id', 'type_id']);
/* -- ND Experiment */
ALTER TABLE nd_experiment_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN nd_experiment_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Data Collector (AGRO:00000379) indicates that the data in this natural diversity experiment was collected by the linked contact.';
ALTER TABLE nd_experiment_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN nd_experiment_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique ND Experiment - contact combination.';
ALTER TABLE nd_experiment_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
CALL addUniqueLinkerConstraint('nd_experiment_contact', 'nd_experiment_contact_c1', ARRAY['nd_experiment_id', 'contact_id', 'type_id']);
/* -- Project */
ALTER TABLE project_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Funder (EFO:0001736) indicates that the linked contact organization funded the research described in this project.';
ALTER TABLE project_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN project_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique project - contact combination.';
ALTER TABLE project_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_contact DROP CONSTRAINT project_contact_c1;
CALL addUniqueLinkerConstraint('project_contact', 'project_contact_c1', ARRAY['project_id', 'contact_id', 'type_id']);
/* -- Pubauthor */
ALTER TABLE pubauthor_contact ADD COLUMN type_id bigint;
COMMENT ON COLUMN pubauthor_contact.type_id IS 'Indicates the type of linkage such as the role of the contact. For example, a type_id referencing the term Exact (NCIT:C86021) indicates that the linked contact represents the same person as the author of the publication.';
ALTER TABLE pubauthor_contact ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN pubauthor_contact.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique publication author - contact combination.';
ALTER TABLE pubauthor_contact ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE pubauthor_contact DROP CONSTRAINT pubauthor_contact_c1;
CALL addUniqueLinkerConstraint('pubauthor_contact', 'pubauthor_contact_c1', ARRAY['pubauthor_id', 'contact_id', 'type_id']);
/* Project Linkers */
/* -- Features */
ALTER TABLE project_feature ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_feature.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Reference Object (NCIT:C48294) indicates that the linked project references this feature in the course of their research.';
ALTER TABLE project_feature ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN project_feature.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique feature - project combination.';
ALTER TABLE project_feature ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_feature DROP CONSTRAINT project_feature_c1;
CALL addUniqueLinkerConstraint('project_feature', 'project_feature_c1', ARRAY['feature_id', 'project_id', 'type_id']);
/* -- Publications */
ALTER TABLE project_pub ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_pub.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Reference Object (NCIT:C48294) indicates that the linked project references this publication in the course of their research.';
ALTER TABLE project_pub ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN project_pub.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique publication - project combination.';
ALTER TABLE project_pub ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_pub DROP CONSTRAINT project_pub_c1;
CALL addUniqueLinkerConstraint('project_pub', 'project_pub_c1', ARRAY['pub_id', 'project_id', 'type_id']);
/* -- ND Experiments */
ALTER TABLE nd_experiment_project ADD COLUMN type_id bigint;
COMMENT ON COLUMN nd_experiment_project.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Output (REPRODUCEME:Output) indicates that the linked project carried out this experiment in the course of their research.';
ALTER TABLE nd_experiment_project ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN nd_experiment_project.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique nd experiment - project combination.';
ALTER TABLE nd_experiment_project ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE nd_experiment_project DROP CONSTRAINT nd_experiment_project_c1;
CALL addUniqueLinkerConstraint('nd_experiment_project', 'nd_experiment_project_c1', ARRAY['nd_experiment_id', 'project_id', 'type_id']);
/* -- Analysis */
/*    Already has a rank */
COMMENT ON COLUMN project_analysis.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique analysis - project combination.';
ALTER TABLE project_analysis ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_analysis.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Output (REPRODUCEME:Output) indicates that the linked project carried out this analysis in the course of their research.';
ALTER TABLE project_analysis ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_analysis DROP CONSTRAINT project_analysis_c1;
CALL addUniqueLinkerConstraint('project_analysis', 'project_analysis_c1', ARRAY['analysis_id', 'project_id', 'type_id']);
/* -- Stock */
ALTER TABLE project_stock ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_stock.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Output (REPRODUCEME:Output) indicates that the linked project produced this genetic stock (e.g. bred a new cultivar, extracted a DNA sample) in the course of their research.';
ALTER TABLE project_stock ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN project_stock.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique stock - project combination.';
ALTER TABLE project_stock ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_stock DROP CONSTRAINT project_stock_c1;
CALL addUniqueLinkerConstraint('project_stock', 'project_stock_c1', ARRAY['stock_id', 'project_id', 'type_id']);
/* -- Assay */
ALTER TABLE assay_project ADD COLUMN type_id bigint;
COMMENT ON COLUMN assay_project.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Output (REPRODUCEME:Output) indicates that the linked project carried out this assay in the course of their research.';
ALTER TABLE assay_project ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN assay_project.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique assay - project combination.';
ALTER TABLE assay_project ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE assay_project DROP CONSTRAINT assay_project_c1;
CALL addUniqueLinkerConstraint('assay_project', 'assay_project_c1', ARRAY['assay_id', 'project_id', 'type_id']);
/* -- Dbxref */
ALTER TABLE project_dbxref ADD COLUMN type_id bigint;
COMMENT ON COLUMN project_dbxref.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term doi (REPRODUCEME:doi) indicates that the linked dbxref is a persistent identifier for this project.';
ALTER TABLE project_dbxref ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN project_dbxref.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique dbxref - project combination.';
ALTER TABLE project_dbxref ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE project_dbxref DROP CONSTRAINT project_dbxref_c1;
CALL addUniqueLinkerConstraint('project_dbxref', 'project_dbxref_c1', ARRAY['dbxref_id', 'project_id', 'type_id']);
/* -- biomaterial_project */
ALTER TABLE biomaterial_project ADD COLUMN type_id bigint;
COMMENT ON COLUMN biomaterial_project.type_id IS 'Indicates the type of linkage such as the way the project uses this item. For example, a type_id referencing the term Output (REPRODUCEME:Output) indicates that the linked project collected this genetic stock (e.g. collected a field sample, extracted a DNA sample) in the course of their research.';
ALTER TABLE biomaterial_project ADD COLUMN rank int DEFAULT 0;
COMMENT ON COLUMN biomaterial_project.rank IS 'Indicates the ordering of contacts with the same type_id. Currently this is not part of the unique key; therefore, there should only be one rank per unique biomaterial - project combination.';
ALTER TABLE biomaterial_project ADD FOREIGN KEY (type_id) REFERENCES cvterm (cvterm_id) ON DELETE SET NULL;
ALTER TABLE biomaterial_project DROP CONSTRAINT biomaterial_project_c1;
CALL addUniqueLinkerConstraint('biomaterial_project', 'biomaterial_project_c1', ARRAY['biomaterial_id', 'project_id', 'type_id']);
