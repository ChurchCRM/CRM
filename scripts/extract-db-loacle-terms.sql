select DISTINCT cfg_tooltip as term, "" as translation, "config_cfg" as cntx from config_cfg
union all
select DISTINCT qry_Name as term, "" as translation, "query_qry" as cntx   from query_qry
union all
select DISTINCT qry_Description as term, "" as translation, "query_qry" as cntx    from query_qry
union all
select DISTINCT content_english as term, "" as translation, "menuconfig_mcf" as cntx    from menuconfig_mcf;