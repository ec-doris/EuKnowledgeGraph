CREATE TABLE IF NOT EXISTS /*_*/wbqc_constraints (
  constraint_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  constraint_guid varbinary(63) NOT NULL,
  pid int(11) NOT NULL,
  constraint_type_qid varbinary(25) NOT NULL,
  constraint_parameters text DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wbqc_constraints_pid_index ON /*_*/wbqc_constraints (pid);

CREATE UNIQUE INDEX /*i*/wbqc_constraints_guid_uniq ON /*_*/wbqc_constraints (constraint_guid);
