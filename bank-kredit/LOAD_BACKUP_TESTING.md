# 📊 LOAD & BACKUP TESTING PLAN

**Test Date:** [Schedule testing window]  
**Environment:** Production-like test server  
**Status:** READY FOR EXECUTION

---

## 1. LOAD TESTING OBJECTIVES

### Goals
- ✓ Verify system handles peak concurrent users
- ✓ Identify performance bottlenecks
- ✓ Ensure database stability under load
- ✓ Validate caching and optimization
- ✓ Measure response times at scale

### Success Criteria
- [ ] **Concurrent Users**: Support 50+ simultaneous users
- [ ] **Response Time**: Dashboard <2 sec, Forms <3 sec under load
- [ ] **Database Connections**: Stable, no connection pool exhaustion
- [ ] **Memory Usage**: Stays within 80% of available RAM
- [ ] **CPU Usage**: Peaks below 85%
- [ ] **Error Rate**: <0.1% (1 error per 1000 requests)

---

## 2. LOAD TEST SCENARIOS

### 2.1 Scenario A: Normal Operating Load
**Simulates typical weekday usage**

```
Configuration:
- Concurrent Users: 20
- Test Duration: 10 minutes
- Think Time: 3-5 seconds between actions
- Ramp-up: 2 minutes

Expected Behavior:
✓ All users can login successfully
✓ Dashboards load within 2 seconds
✓ Reports generate within 5 seconds
✓ No timeouts or connection errors
```

**Actions per User:**
1. Login (2 min mark)
2. View dashboard (hold 1 min)
3. Navigate to pengajuan list (load)
4. View single pengajuan details (load)
5. View approval history (load)
6. Generate report (load 5 sec)
7. Logout (2 min before end)

### 2.2 Scenario B: Peak Load
**Simulates end-of-month approval rush**

```
Configuration:
- Concurrent Users: 50
- Test Duration: 15 minutes
- Think Time: 2-3 seconds
- Ramp-up: 3 minutes

Expected Behavior:
✓ System remains responsive
✓ Occasional slight slowdown acceptable (<5 sec)
✓ No database locks
✓ No session corruption
```

**Actions per User:**
1. Login during ramp-up
2. Multiple pengajuan reviews
3. Approvals/rejections
4. Report generation
5. History viewing

### 2.3 Scenario C: Stress Test
**Pushes system beyond normal limits**

```
Configuration:
- Concurrent Users: 100+
- Test Duration: 5 minutes
- No think time (rapid-fire requests)
- Ramp-up: 1 minute

Expected Behavior:
✓ Graceful degradation (slow but functional)
✓ No data corruption
✓ Clear error messages when limit reached
✓ Recovery when load decreases
```

**Actions:**
- Rapid sequential page requests
- Multiple simultaneous form submissions
- Large report generation
- Concurrent approval operations

### 2.4 Scenario D: Sustained Load
**Realistic all-day load**

```
Configuration:
- Concurrent Users: 30
- Test Duration: 1 hour
- Variable think time: 2-10 seconds
- Simulate morning/afternoon peaks

Expected Behavior:
✓ Memory stable (no memory leaks)
✓ Connection pool healthy
✓ Database handles continuous queries
✓ No session accumulation issues
```

---

## 3. LOAD TESTING TOOLS & SETUP

### Recommended Tools
- **Apache JMeter** (free, open-source)
- **LoadRunner** (enterprise)
- **k6** (modern, cloud-based)
- **Locust** (Python-based)

### Setup Instructions (JMeter)

```bash
# 1. Install JMeter
# Download from: https://jmeter.apache.org/download_jmeter.cgi

# 2. Create test plan
# File > New > Test Plan

# 3. Add Thread Group
# - Number of Threads: 20 (for normal load)
# - Ramp-up: 120 seconds
# - Loop Count: 1

# 4. Add HTTP Sampler for each request
# - Server: localhost:8000 (or production-like server)
# - Method: GET/POST
# - Path: /auth/login.php, /analis/dashboard.php, etc.

# 5. Add Listeners
# - View Results Tree
# - Aggregate Report
# - Graph Results

# 6. Run Test
# jmeter -n -t test_plan.jmx -l results.csv -j jmeter.log
```

### Monitoring During Test

**Metrics to Track:**
```sql
-- 1. Database Connections
SHOW PROCESSLIST; -- Count active connections

-- 2. Query Performance
-- Enable slow query log:
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;

-- 3. Memory/CPU (on server)
# Linux:
top -b -n 1 | head -20
free -h
df -h

# Windows:
tasklist
Get-Process | Sort-Object -Property WS -Descending | Select -First 10
```

---

## 4. PERFORMANCE METRICS & BASELINES

### Expected Baselines (from previous tests)

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| Response Time (Dashboard) | <2 sec | 2-3 sec | >3 sec |
| Response Time (Forms) | <3 sec | 3-4 sec | >4 sec |
| Response Time (Reports) | <5 sec | 5-8 sec | >8 sec |
| Database Query Time | <500ms | 500ms-1s | >1s |
| CPU Usage | <70% | 70-85% | >85% |
| Memory Usage | <60% | 60-80% | >80% |
| Error Rate | <0.1% | 0.1-1% | >1% |
| Concurrent Connections | <100 | 100-150 | >150 |

### Measurement Points
- [ ] **Response Time**: Sampler.response_time
- [ ] **Throughput**: Requests per second (RPS)
- [ ] **Error Rate**: Failed requests / Total requests
- [ ] **Latency**: Average response time distribution
- [ ] **Database Queries**: Slow query log analysis

---

## 5. LOAD TEST EXECUTION

### Pre-Test Checklist
- [ ] Test environment isolated from production
- [ ] Database backed up before testing
- [ ] Monitoring tools activated
- [ ] Sufficient test data loaded (at least 100 pengajuan)
- [ ] Network stable, no other traffic
- [ ] All team members briefed

### Test Execution Log

**Scenario A: Normal Load**
```
Start Time: [___:___]
End Time: [___:___]
Total Duration: 10 minutes
Target Users: 20
Actual Users: [___]

Metrics:
- Avg Response Time: [___] sec
- Max Response Time: [___] sec
- Min Response Time: [___] sec
- Error Count: [___]
- Error Rate: [___]%
- Throughput: [___] RPS
- CPU Peak: [___]%
- Memory Peak: [___]%

Issues: ___________________________________________________________
```

**Scenario B: Peak Load**
```
Start Time: [___:___]
End Time: [___:___]
Total Duration: 15 minutes
Target Users: 50
Actual Users: [___]

Metrics:
- Avg Response Time: [___] sec
- Max Response Time: [___] sec
- Error Count: [___]
- Error Rate: [___]%
- Throughput: [___] RPS
- Database Connections: [___]

Issues: ___________________________________________________________
```

**Scenario C: Stress Test**
```
Start Time: [___:___]
End Time: [___:___]
Target Users: 100+
Actual Peak Users: [___]

Degradation Notes:
- Response time increased at user count: [___]
- First errors at user count: [___]
- System recovered: [ ] Yes [ ] No

Issues: ___________________________________________________________
```

---

## 6. BACKUP & RECOVERY TESTING

### 6.1 Backup Verification

**Test Case: BACKUP-001 - Backup Creation**
```
Steps:
1. Login to admin panel
2. Navigate to Backup page
3. Click "Create Backup Now"
4. Verify backup file created
5. Check backup file size >1MB
6. Verify backup in /backups/ directory

Expected Result:
✓ Backup file: backup_YYYY-MM-DD_HH-MM-SS.sql
✓ File size reasonable (not 0 KB, not 1 KB)
✓ File permissions 644
✓ Creation timestamp current
```

**Test Case: BACKUP-002 - Backup Integrity**
```
Steps:
1. Open backup file
2. Verify it contains:
   - SQL header comments
   - CREATE TABLE statements
   - INSERT statements for data
   - Foreign key definitions
3. Check file is not corrupted

Verification Queries:
grep "CREATE TABLE" backup.sql
grep "INSERT INTO" backup.sql
grep "CONSTRAINT" backup.sql

Expected Result:
✓ All tables present
✓ Data rows present
✓ No corruption indicators
```

**Test Case: BACKUP-003 - Backup Size Validation**
```
Metrics:
- Empty DB size: ~100 KB
- Backup with 100 applications: ~500 KB - 1 MB
- Backup with 1000 applications: ~3-5 MB

Check:
If backup size seems wrong, may indicate:
- Memory leak (accumulating old data)
- Missing compression
- Duplicate records
```

### 6.2 Restore Testing

**Test Case: RESTORE-001 - Full Database Restore**
```
Environment: Test server (NOT production)

Steps:
1. Create test database copy
   CREATE DATABASE test_restore LIKE bank_kredit;
   
2. Restore from backup
   mysql test_restore < backup_YYYY-MM-DD.sql
   
3. Verify restoration
   SELECT COUNT(*) FROM pengajuan_kredit;
   SELECT COUNT(*) FROM audit_log;
   SELECT COUNT(*) FROM users;
   
4. Check data integrity
   - All foreign keys valid
   - No orphaned records
   - Counts match original backup

Expected Result:
✓ All tables restored
✓ All data restored accurately
✓ No data loss
✓ Foreign keys intact
```

**Test Case: RESTORE-002 - Partial Data Restore**
```
Steps:
1. Extract specific table from backup:
   sed -n '/^-- Dump of table users/,/^-- Dump of table/p' backup.sql > users_only.sql
   
2. Restore to fresh database:
   mysql test_db < users_only.sql
   
3. Verify restoration

Expected Result:
✓ Specific table restored
✓ Other tables unaffected
✓ Can cherry-pick data as needed
```

**Test Case: RESTORE-003 - Point-in-Time Recovery**
```
Concept: Restore to specific date/time

Steps:
1. Identify last backup before incident: backup_2026-04-20_00-00-00.sql
2. Extract transactions up to specific time from binary log
3. Combine backup + binary log for PITR

Note: Requires binary logging enabled in MySQL
```

### 6.3 Disaster Recovery Scenarios

**Scenario 1: Database Corruption**
```
Cause: Unexpected power loss, hardware failure

Recovery Steps:
1. Verify backup integrity
   mysqlcheck -u root -p test_recovery
   
2. Restore from most recent backup
   mysql < backup_latest.sql
   
3. Check data consistency
4. Review audit logs for lost time period
5. Notify affected users of potential missing data

Recovery Time Objective (RTO): 30 minutes
Recovery Point Objective (RPO): 24 hours (daily backups)
```

**Scenario 2: Accidental Data Deletion**
```
Cause: User accidentally deletes applications

Recovery Steps:
1. Identify deletion time from audit log
2. Find most recent backup BEFORE deletion
3. Restore users and pengajuan tables to point-in-time
4. Merge with current database (carefully)
5. Verify data consistency

RTO: 1 hour
RPO: Last backup before deletion
```

**Scenario 3: Malware/Ransomware**
```
Cause: System compromised, data encrypted

Recovery Steps:
1. Isolate system from network
2. Do NOT attempt to decrypt or pay ransom
3. Restore from clean backup (before infection)
4. Perform security audit
5. Apply security patches
6. Implement monitoring

RTO: 2-4 hours
RPO: Last clean backup
```

**Scenario 4: Hardware Failure**
```
Cause: Server hard drive fails

Recovery Steps:
1. Boot backup server or cloud instance
2. Restore database from backup
3. Restore application files from repository/backup
4. Verify all services operational
5. Update DNS/load balancer to new server

RTO: 1 hour
RPO: 24 hours
```

---

## 7. BACKUP SCHEDULE RECOMMENDATIONS

### Daily Backup
```
Time: 02:00 AM (off-peak)
Type: Full backup
Retention: 7 days
Storage: Local + cloud
Method: Automated script in crontab
```

### Weekly Backup
```
Time: Sunday 03:00 AM
Type: Full backup
Retention: 4 weeks
Storage: Archive storage
Method: Automated with email confirmation
```

### Monthly Backup
```
Time: 1st of month, 04:00 AM
Type: Full backup + verification
Retention: 12 months
Storage: Offsite secure storage
Method: Manual verification + signed certificate
```

### Backup Verification Schedule
```
Weekly: Restore to test database, verify integrity
Monthly: Full recovery test, document findings
Quarterly: Disaster recovery drill with team
```

---

## 8. BACKUP SIGN-OFF

### Test Results Summary

| Test | Status | Duration | Data Integrity | Notes |
|------|--------|----------|-----------------|-------|
| Backup Creation | [ ] | [ ] | [ ] | |
| Backup Integrity | [ ] | [ ] | [ ] | |
| Full Restore | [ ] | [ ] | [ ] | |
| Partial Restore | [ ] | [ ] | [ ] | |
| PITR Recovery | [ ] | [ ] | [ ] | |
| Disaster Scenario 1 | [ ] | [ ] | [ ] | |
| Disaster Scenario 2 | [ ] | [ ] | [ ] | |
| Disaster Scenario 3 | [ ] | [ ] | [ ] | |
| Disaster Scenario 4 | [ ] | [ ] | [ ] | |

### Sign-Off

**Tested By:** ___________________  
**Date:** ___________________

**Backup System Status:** [ ] Ready [ ] Needs Work

**RTO/RPO Achievement:**
- [ ] RTO acceptable (<30 min for normal recovery)
- [ ] RPO acceptable (<24 hours)

**Signed:** ___________________  
**Date:** ___________________

---

## 9. PERFORMANCE OPTIMIZATION NEXT STEPS

If tests reveal bottlenecks:

1. **Database Optimization**
   - Add missing indexes
   - Analyze slow queries
   - Consider partitioning large tables
   - Implement query caching

2. **Application Optimization**
   - Cache frequently accessed data
   - Optimize loops and algorithms
   - Implement pagination for large results
   - Use connection pooling

3. **Infrastructure Scaling**
   - Load balancer for multiple app servers
   - Read replicas for database
   - CDN for static assets
   - Message queue for async operations

4. **Monitoring**
   - Real-time performance dashboards
   - Alert thresholds for bottlenecks
   - Continuous profiling in production

---

## 10. REFERENCES

- MySQL Performance: https://dev.mysql.com/doc/
- Apache JMeter Guide: https://jmeter.apache.org/usermanual/
- Backup Best Practices: https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html
